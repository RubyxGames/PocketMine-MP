<?php

declare(strict_types=1);

namespace pocketmine\event\player;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\form\ddui\DduiForm;
use pocketmine\player\Player;

/**
 * Fired when a DDUI form produces a user-facing outcome (e.g. MessageBox button choice).
 */
class DduiFormResponseEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		Player $player,
		private readonly int $formId,
		private readonly DduiForm $form,
		private readonly mixed $response,
	){
		$this->player = $player;
	}

	public function getFormId() : int{
		return $this->formId;
	}

	public function getForm() : DduiForm{
		return $this->form;
	}

	public function getResponse() : mixed{
		return $this->response;
	}
}
