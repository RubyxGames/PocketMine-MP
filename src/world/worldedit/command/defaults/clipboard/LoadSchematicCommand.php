<?php

namespace pocketmine\world\worldedit\command\defaults\clipboard;

use Generator;
use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\StringCommandFlag;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\WorldEdit;
use pocketmine\world\worldedit\result\EditTaskResult;
use pocketmine\world\worldedit\schematic\SchematicFileAdapter;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\task\schematic\SchematicLoadTask;
use pocketmine\world\worldedit\utils\MixedUtils;

class LoadSchematicCommand extends WorldEditCommand
{
	public function __construct()
	{
		parent::__construct("/loadschematic", [KnownPermissions::PERMISSION_READDISK, KnownPermissions::PERMISSION_CLIPBOARD], ["/load"]);
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$schematicName = $flags->hasFlag("schematic") ? pathinfo($flags->getStringFlag("schematic"), PATHINFO_FILENAME) : "";
		if ($schematicName === "" || !SchematicFileAdapter::schematicExists(WorldEdit::getSchematicPath() . $schematicName)) {
			$session->sendMessage("unknown-schematic", ["{schematic}" => $schematicName, "{known}" => implode(", ", SchematicFileAdapter::getSchematicList())]);
			return;
		}

		$session->runTask(new SchematicLoadTask(WorldEdit::getSchematicPath() . $schematicName))->then(function (EditTaskResult $result) use ($session) {
			$session->sendMessage("blocks-copied", ["{time}" => $result->getFormattedTime(), "{changed}" => MixedUtils::humanReadable($result->getAffected())]);
			$session->setClipboard($result->getSelection());
		});
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [];
	}

	/**
	 * @param CommandFlagCollection $flags
	 * @param Session               $session
	 * @param string[]              $args
	 * @return Generator<CommandFlag>
	 */
	public function parseArguments(CommandFlagCollection $flags, Session $session, array $args): Generator
	{
		if (isset($args[0])) {
			yield StringCommandFlag::with($args[0], "schematic");
		}
	}
}