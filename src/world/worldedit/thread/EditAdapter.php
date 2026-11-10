<?php

namespace pocketmine\world\worldedit\thread;

use pocketmine\world\worldedit\thread\block\BlockStateTranslationManager;
use pocketmine\scheduler\Task;

class EditAdapter extends Task
{
	public function onRun(): void
	{
		EditThread::getInstance()->parseOutput();
		BlockStateTranslationManager::tick();
		MainThreadTaskScheduler::getInstance()->tick();
	}
}