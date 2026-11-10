<?php

namespace pocketmine\world\worldedit\command\defaults\generation;

use pocketmine\world\worldedit\command\WorldEditCommand;
use pocketmine\world\worldedit\command\FlagArgumentParser;
use pocketmine\world\worldedit\command\flags\CommandFlag;
use pocketmine\world\worldedit\command\flags\CommandFlagCollection;
use pocketmine\world\worldedit\command\flags\FloatCommandFlag;
use pocketmine\world\worldedit\command\flags\PatternCommandFlag;
use pocketmine\world\worldedit\command\flags\SingularCommandFlag;
use pocketmine\world\worldedit\command\KnownPermissions;
use pocketmine\world\worldedit\math\BlockOffsetVector;
use pocketmine\world\worldedit\math\BlockVector;
use pocketmine\world\worldedit\pattern\logic\selection\SidesPattern;
use pocketmine\world\worldedit\selection\Cube;
use pocketmine\world\worldedit\session\Session;
use pocketmine\world\worldedit\task\editing\SetTask;

class CubeCommand extends WorldEditCommand
{
	use FlagArgumentParser;

	public function __construct()
	{
		parent::__construct("/cube", [KnownPermissions::PERMISSION_GENERATE, KnownPermissions::PERMISSION_EDIT], ["/cb"]);
		$this->flagOrder = ["size" => true, "pattern" => true];
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		$pattern = $flags->hasFlag("hollow") ? new SidesPattern($flags->getFloatFlag("thickness"), [$flags->getPatternFlag("pattern")]) : $flags->getPatternFlag("pattern");
		$size = $flags->getFloatFlag("size") - 1;
		$offsetMax = new BlockOffsetVector((int) ceil($size / 2), (int) ceil($size / 2), (int) ceil($size / 2));
		$offsetMin = new BlockOffsetVector((int) floor($size / 2), (int) floor($size / 2), (int) floor($size / 2));
		$session->runSettingTask(new SetTask(new Cube($session->asPlayer()->getWorld()->getFolderName(), BlockVector::fromVector($session->asPlayer()->getPosition())->offset($offsetMax), BlockVector::fromVector($session->asPlayer()->getPosition())->offset($offsetMin->negate())), $pattern));
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"size" => new FloatCommandFlag("size", ["radius"], "s"),
			"pattern" => new PatternCommandFlag("pattern", [], "p"),
			"hollow" => new SingularCommandFlag("hollow", [], "h"),
			"thickness" => FloatCommandFlag::default(1.0, "thickness", ["thick"], "t")
		];
	}
}