<?php

namespace pocketmine\world\worldedit\task;

trait EditThreadExclusive
{
	public function calculateEffectiveComplexity(): int
	{
		return 0;
	}

	public function canExecuteOnMainThread(): bool
	{
		return false;
	}
}