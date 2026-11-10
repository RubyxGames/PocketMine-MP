<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

final class DduiSession{
	public function __construct(
		private int $formId,
		private ?int $dataInstanceId,
		private string $screenId,
		private string $dataStoreName,
		private string $clientProperty,
		private DduiForm $form,
		private int $openedAtTick,
		private DduiSessionState $state = DduiSessionState::OPENING,
	){}

	public function getFormId() : int{ return $this->formId; }

	public function getDataInstanceId() : ?int{ return $this->dataInstanceId; }

	public function getScreenId() : string{ return $this->screenId; }

	public function getDataStoreName() : string{ return $this->dataStoreName; }

	public function getClientProperty() : string{ return $this->clientProperty; }

	public function getForm() : DduiForm{ return $this->form; }

	public function getOpenedAtTick() : int{ return $this->openedAtTick; }

	public function getState() : DduiSessionState{ return $this->state; }

	public function setState(DduiSessionState $state) : void{
		$this->state = $state;
	}

	public function matchesProperty(string $dataStoreName, string $property) : bool{
		return $this->dataStoreName === $dataStoreName && $this->clientProperty === $property;
	}
}
