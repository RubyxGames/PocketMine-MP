<?php

namespace pocketmine\world\worldedit\world\clientblock;

use pocketmine\scheduler\AsyncTask;

class RegistryUpdateTask extends AsyncTask
{
	public function onRun(): void
	{
		Registry::registerToNetwork();
	}
}