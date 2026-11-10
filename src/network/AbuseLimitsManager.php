<?php

declare(strict_types=1);

namespace pocketmine\network;

use pocketmine\player\Player;
use pocketmine\ServerConfigGroup;
use pocketmine\utils\TextFormat;
use function spl_object_id;

/**
 * Configurable chat/command rate limits (list item 39).
 */
final class AbuseLimitsManager{

	private bool $chatEnabled;
	private float $chatPerSecond;
	private float $chatBurst;
	private bool $commandsEnabled;
	private float $commandsPerSecond;
	private float $commandsBurst;

	/** @var AbuseRateLimiter[] */
	private array $chatLimiters = [];
	/** @var AbuseRateLimiter[] */
	private array $commandLimiters = [];

	public function __construct(ServerConfigGroup $cfg){
		$this->chatEnabled = $cfg->getPropertyBool("abuse-limits.chat.enabled", true);
		$this->chatPerSecond = max(0.1, (float) $cfg->getPropertyInt("abuse-limits.chat.max-per-second", 2));
		// burst may be int in yaml; also allow float via getProperty
		$chatBurst = $cfg->getProperty("abuse-limits.chat.burst", 4);
		$this->chatBurst = max($this->chatPerSecond, (float) $chatBurst);

		$this->commandsEnabled = $cfg->getPropertyBool("abuse-limits.commands.enabled", true);
		$this->commandsPerSecond = max(0.1, (float) $cfg->getPropertyInt("abuse-limits.commands.max-per-second", 4));
		$cmdBurst = $cfg->getProperty("abuse-limits.commands.burst", 8);
		$this->commandsBurst = max($this->commandsPerSecond, (float) $cmdBurst);
	}

	public function onPlayerQuit(Player $player) : void{
		$id = spl_object_id($player);
		unset($this->chatLimiters[$id], $this->commandLimiters[$id]);
	}

	/**
	 * @return bool true if chat is allowed
	 */
	public function allowChat(Player $player) : bool{
		if(!$this->chatEnabled){
			return true;
		}
		$id = spl_object_id($player);
		$limiter = $this->chatLimiters[$id] ??= new AbuseRateLimiter("chat", $this->chatPerSecond, $this->chatBurst);
		if($limiter->tryConsume()){
			return true;
		}
		if($limiter->shouldWarn()){
			$player->sendMessage(TextFormat::RED . "You are sending chat too quickly.");
			$player->getNetworkSession()->getLogger()->warning("Chat rate limit exceeded");
		}else{
			$player->getNetworkSession()->getLogger()->debug("Chat rate limit exceeded (suppressed warn)");
		}
		return false;
	}

	/**
	 * @return bool true if command is allowed
	 */
	public function allowCommand(Player $player) : bool{
		if(!$this->commandsEnabled){
			return true;
		}
		$id = spl_object_id($player);
		$limiter = $this->commandLimiters[$id] ??= new AbuseRateLimiter("commands", $this->commandsPerSecond, $this->commandsBurst);
		if($limiter->tryConsume()){
			return true;
		}
		if($limiter->shouldWarn()){
			$player->sendMessage(TextFormat::RED . "You are sending commands too quickly.");
			$player->getNetworkSession()->getLogger()->warning("Command rate limit exceeded");
		}else{
			$player->getNetworkSession()->getLogger()->debug("Command rate limit exceeded (suppressed warn)");
		}
		return false;
	}
}
