<?php

declare(strict_types=1);

namespace pocketmine\event\player;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\form\ddui\DduiForm;
use pocketmine\player\Player;

/**
 * Called when the client reports a DDUI screen was closed (or server evicted a timed-out session).
 */
class DduiFormCloseEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		Player $player,
		private readonly int $formId,
		private readonly DduiForm $form,
		private readonly ?string $closeReason,
	){
		$this->player = $player;
	}

	public function getFormId() : int{
		return $this->formId;
	}

	public function getForm() : DduiForm{
		return $this->form;
	}

	public function getCloseReason() : ?string{
		return $this->closeReason;
	}
}
