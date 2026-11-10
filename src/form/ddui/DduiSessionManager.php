<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\event\player\DduiFormClientUpdateEvent;
use pocketmine\event\player\DduiFormCloseEvent;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use function count;

final class DduiSessionManager{
	private int $nextFormId = 1;

	/** @var DduiSession[] */
	private array $sessionsByFormId = [];

	private ?int $activeFormId = null;

	private ?DduiForm $pendingForm = null;

	private int $inboundUpdatesThisTick = 0;

	private int $lastInboundTick = -1;

	private int $outboundUpdatesThisTick = 0;

	private int $lastOutboundTick = -1;

	public function __construct(
		private NetworkSession $session,
		private Server $server,
	){}

	public function show(DduiForm $form) : bool{
		$player = $this->session->getPlayer();
		if($player === null || !$this->session->isConnected()){
			return false;
		}

		if(!DduiSupport::isProtocolSupported($this->session->getProtocolId())){
			return false;
		}

		if($this->activeFormId !== null){
			$this->close($this->activeFormId, notifyClient: true);
			$delay = DduiSettings::getReplaceDelayTicks();
			if($delay <= 0){
				return $this->showImmediate($form);
			}
			$this->pendingForm = $form;
			$pending = $form;
			$this->server->getCorePluginScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($pending) : void{
				if($this->pendingForm !== $pending){
					return;
				}
				$this->pendingForm = null;
				if(!$this->session->isConnected()){
					return;
				}
				$this->showImmediate($pending);
			}), $delay);

			return true;
		}

		return $this->showImmediate($form);
	}

	private function showImmediate(DduiForm $form) : bool{
		$player = $this->session->getPlayer();
		if($player === null){
			return false;
		}

		$this->session->onCloseLegacyForms();

		$protocolId = $this->session->getProtocolId();
		$formId = $this->nextFormId++;
		$dataInstanceId = DduiSupport::supportsDataInstanceId($protocolId) ? $formId : null;
		$clientProperty = DduiProperties::clientPropertyName($form->getRootPropertyName(), $protocolId, $formId);

		$dduiSession = new DduiSession(
			$formId,
			$dataInstanceId,
			$form->getScreenId(),
			DduiProperties::dataStoreName($form->getScreenId()),
			$clientProperty,
			$form,
			$this->server->getTick()
		);
		$this->sessionsByFormId[$formId] = $dduiSession;
		$this->activeFormId = $formId;

		DduiNetworkAdapter::sendOpen($this->session, $dduiSession, $form->buildInitialValue());
		$dduiSession->setState(DduiSessionState::OPEN);

		if($form instanceof DduiFormOnOpen){
			$form->onDduiOpen($player, $dduiSession);
		}

		return true;
	}

	public function close(int $formId, bool $notifyClient = true) : void{
		$session = $this->sessionsByFormId[$formId] ?? null;
		if($session === null || $session->getState() === DduiSessionState::CLOSED){
			return;
		}

		$session->setState(DduiSessionState::CLOSED);
		unset($this->sessionsByFormId[$formId]);
		if($this->activeFormId === $formId){
			$this->activeFormId = null;
		}

		if($notifyClient){
			DduiNetworkAdapter::sendClose($this->session, $formId);
		}
	}

	public function closeAll() : void{
		$this->pendingForm = null;
		if(count($this->sessionsByFormId) === 0){
			return;
		}
		foreach(array_keys($this->sessionsByFormId) as $formId){
			$this->close($formId, true);
		}
	}

	public function handleServerboundDataStore(DataStoreUpdate $update) : bool{
		$tick = $this->server->getTick();
		if($tick !== $this->lastInboundTick){
			$this->lastInboundTick = $tick;
			$this->inboundUpdatesThisTick = 0;
		}
		if(++$this->inboundUpdatesThisTick > DduiSettings::getMaxInboundUpdatesPerTick()){
			return true;
		}

		$name = $update->getName();
		$property = $update->getProperty();
		foreach($this->sessionsByFormId as $formId => $session){
			if(!$session->matchesProperty($name, $property)){
				continue;
			}
			if($session->getState() !== DduiSessionState::OPEN){
				return true;
			}
			$player = $this->session->getPlayer();
			if($player === null){
				return true;
			}

			$ev = new DduiFormClientUpdateEvent($player, $formId, $session->getForm(), $update);
			$ev->call();
			if($ev->isCancelled()){
				return true;
			}

			$session->getForm()->handleClientUpdate($player, $session, $update);
			return true;
		}

		return false;
	}

	public function handleScreenClosed(int $formId, string $closeReason) : bool{
		$session = $this->sessionsByFormId[$formId] ?? null;
		if($session === null){
			return false;
		}

		$player = $this->session->getPlayer();
		if($player === null){
			return false;
		}

		$ev = new DduiFormCloseEvent($player, $formId, $session->getForm(), $closeReason);
		$ev->call();
		if($ev->isCancelled()){
			return true;
		}

		$this->close($formId, notifyClient: true);
		$session->getForm()->handleClose($player, $session, $closeReason);

		return true;
	}

	public function tick(int $currentTick) : void{
		$timeout = DduiSettings::getSessionTimeoutTicks();
		if($timeout <= 0 || count($this->sessionsByFormId) === 0){
			return;
		}

		foreach($this->sessionsByFormId as $formId => $session){
			if($session->getState() !== DduiSessionState::OPEN){
				continue;
			}
			if(($currentTick - $session->getOpenedAtTick()) <= $timeout){
				continue;
			}
			$player = $this->session->getPlayer();
			if($player === null){
				$this->close($formId, true);
				continue;
			}
			$ev = new DduiFormCloseEvent($player, $formId, $session->getForm(), "timeout");
			$ev->call();
			if($ev->isCancelled()){
				continue;
			}
			$this->close($formId, true);
			$session->getForm()->handleClose($player, $session, "timeout");
		}
	}

	public function hasPendingForm(int $formId) : bool{
		return isset($this->sessionsByFormId[$formId]);
	}

	public function getActiveSession() : ?DduiSession{
		if($this->activeFormId === null){
			return null;
		}
		$session = $this->sessionsByFormId[$this->activeFormId] ?? null;
		if($session === null || $session->getState() !== DduiSessionState::OPEN){
			return null;
		}
		return $session;
	}

	/**
	 * @param DataStoreUpdate[] $updates
	 * @phpstan-param list<DataStoreUpdate> $updates
	 */
	public function pushClientUpdates(array $updates) : bool{
		if(count($updates) === 0){
			return true;
		}
		$active = $this->getActiveSession();
		if($active === null){
			return false;
		}

		$tick = $this->server->getTick();
		if($tick !== $this->lastOutboundTick){
			$this->lastOutboundTick = $tick;
			$this->outboundUpdatesThisTick = 0;
		}
		if($this->outboundUpdatesThisTick + count($updates) > DduiSettings::getMaxOutboundUpdatesPerTick()){
			return false;
		}

		foreach($updates as $update){
			if(!$active->matchesProperty($update->getName(), $update->getProperty())){
				return false;
			}
		}

		$this->outboundUpdatesThisTick += count($updates);
		DduiNetworkAdapter::pushUpdates($this->session, $updates);
		return true;
	}

	public function shutdown() : void{
		$this->pendingForm = null;
		$this->sessionsByFormId = [];
		$this->activeFormId = null;
	}
}
