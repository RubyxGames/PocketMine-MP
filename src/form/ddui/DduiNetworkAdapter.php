<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundDataDrivenUICloseScreenPacket;
use pocketmine\network\mcpe\protocol\ClientboundDataDrivenUIShowScreenPacket;
use pocketmine\network\mcpe\protocol\ClientboundDataStorePacket;
use pocketmine\network\mcpe\protocol\types\cereal\DynamicValue;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreChange;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreOperation;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate;
use function count;

final class DduiNetworkAdapter{
	private function __construct(){
	}

	public static function sendOpen(NetworkSession $session, DduiSession $dduiSession, DynamicValue $initialValue) : void{
		$protocolId = $session->getProtocolId();
		$change = new DataStoreChange(
			$dduiSession->getDataStoreName(),
			$dduiSession->getClientProperty(),
			1,
			$initialValue
		);
		/** @var DataStoreOperation[] $ops */
		$ops = [$change];
		$session->sendDataPacket(ClientboundDataStorePacket::create($ops));

		$dataInstanceId = DduiSupport::supportsDataInstanceId($protocolId) ? $dduiSession->getDataInstanceId() : null;
		$session->sendDataPacket(ClientboundDataDrivenUIShowScreenPacket::create(
			$dduiSession->getScreenId(),
			$dduiSession->getFormId(),
			$dataInstanceId
		));
	}

	public static function sendClose(NetworkSession $session, ?int $formId) : void{
		if(!DduiSupport::supportsScreenClosed($session->getProtocolId())){
			return;
		}
		$session->sendDataPacket(ClientboundDataDrivenUICloseScreenPacket::create($formId));
	}

	/**
	 * @param DataStoreOperation[] $operations
	 * @phpstan-param list<DataStoreOperation> $operations
	 */
	public static function sendDataStoreOperations(NetworkSession $session, array $operations) : void{
		if(count($operations) === 0){
			return;
		}
		$session->sendDataPacket(ClientboundDataStorePacket::create($operations));
	}

	/**
	 * @param DataStoreUpdate[] $updates
	 * @phpstan-param list<DataStoreUpdate> $updates
	 */
	public static function pushUpdates(NetworkSession $session, array $updates) : void{
		if(count($updates) === 0){
			return;
		}
		/** @var DataStoreOperation[] $ops */
		$ops = $updates;
		self::sendDataStoreOperations($session, $ops);
	}
}
