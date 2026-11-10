<?php

namespace pocketmine\world\worldedit\command\defaults\clipboard;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\exception\InvalidUsageException;
use pocketmine\world\worldedit\command\FlagArgumentParser;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\StringCommandFlag;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\WorldEdit;
use pocketmine\world\worldedit\result\SelectionManipulationResult;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\task\schematic\SchematicSaveTask;
use pocketmine\world\worldedit\utils\MixedUtils;

class SaveSchematicCommand extends WorldEditCommand
{
	use FlagArgumentParser;

	public function __construct()
	{
		parent::__construct("/saveschematic", [KnownPermissions::PERMISSION_WRITEDISK, KnownPermissions::PERMISSION_CLIPBOARD], ["/save"]);
		$this->flagOrder = ["schematic" => true];
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$schematicName = pathinfo($flags->getStringFlag("schematic"), PATHINFO_FILENAME);
		if ($schematicName === "") {
			throw new InvalidUsageException();
		}

		$session->runTask(new SchematicSaveTask($session->getClipboard(), WorldEdit::getSchematicPath() . $schematicName))->then(function (SelectionManipulationResult $result) use ($schematicName, $session): void {
			if ($result->getChanged() === 0) {
				return;
			}
			$session->sendMessage("schematic-created", ["{time}" => $result->getFormattedTime(), "{changed}" => MixedUtils::humanReadable($result->getChanged()), "{name}" => basename($schematicName)]);
		});
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"schematic" => new StringCommandFlag("schematic", ["schem"], "s")
		];
	}
}