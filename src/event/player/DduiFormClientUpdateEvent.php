<?php

declare(strict_types=1);

namespace pocketmine\event\player;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\form\ddui\DduiForm;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate;
use pocketmine\player\Player;

/**
 * Called when the client sends a serverbound DataStore update for an active DDUI session.
 */
class DduiFormClientUpdateEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		Player $player,
		private readonly int $formId,
		private readonly DduiForm $form,
		private readonly DataStoreUpdate $update,
	){
		$this->player = $player;
	}

	public function getFormId() : int{
		return $this->formId;
	}

	public function getForm() : DduiForm{
		return $this->form;
	}

	public function getUpdate() : DataStoreUpdate{
		return $this->update;
	}
}
