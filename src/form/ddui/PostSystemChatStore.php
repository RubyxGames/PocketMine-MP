<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

use pocketmine\player\Player;
use function array_slice;
use function count;
use function preg_replace;
use function strlen;
use function substr;
use function trim;
use function usort;

/**
 * In-memory PostSystem-style mail (test bots). Not persisted across restarts.
 */
final class PostSystemChatStore{
	private const MAX_MESSAGES_STORED = 60;
	private const MAX_MESSAGES_SHOWN = 18;
	private const MAX_MESSAGE_LENGTH = 100;
	private const MAX_CONTACTS = 5;

	/** @var list<array{id: string, name: string}> */
	private const TEST_BOTS = [
		["id" => "bot_test", "name" => "Test Bot"],
		["id" => "bot_steve", "name" => "Steve_99"],
		["id" => "bot_alex", "name" => "AlexBuilder"],
		["id" => "bot_luna", "name" => "LunaCrafts"],
		["id" => "bot_max", "name" => "MaxDigsDeep"],
	];

	/** @var array<string, array<string, list<array{f: string, t: int, m: string}>>> */
	private static array $messages = [];

	/** @var array<string, array<string, int>> */
	private static array $seen = [];

	/** @var array<string, array<string, int>> */
	private static array $cleared = [];

	/** @var array<string, bool> */
	private static array $seeded = [];

	private function __construct(){
	}

	public static function playerId(Player $player) : string{
		return $player->getUniqueId()->toString();
	}

	public static function cleanText(string $text) : string{
		$text = preg_replace('/§./u', '', $text) ?? $text;
		$text = preg_replace('/[\r\n\t]+/', ' ', $text) ?? $text;
		$text = trim($text);
		if(strlen($text) > self::MAX_MESSAGE_LENGTH){
			$text = substr($text, 0, self::MAX_MESSAGE_LENGTH - 1) . "…";
		}
		return $text;
	}

	/**
	 * @return list<array{id: string, name: string, unread: bool, msgs: list<array{f: string, t: int, m: string}>}>
	 */
	public static function getContacts(Player $player, ?string $openId) : array{
		self::seedDemo($player);
		$me = self::playerId($player);
		$list = [];
		foreach(self::TEST_BOTS as $bot){
			$id = $bot["id"];
			$msgs = self::conversationMessages($me, $id);
			$last = count($msgs) > 0 ? $msgs[count($msgs) - 1] : null;
			$seenAt = self::$seen[$me][$id] ?? 0;
			$unread = $id !== $openId && $last !== null && $last["f"] !== $me && $last["t"] > $seenAt;
			$list[] = [
				"id" => $id,
				"name" => $bot["name"],
				"unread" => $unread,
				"msgs" => $msgs,
			];
		}
		usort($list, static function(array $a, array $b) : int{
			return ($b["unread"] <=> $a["unread"])
				?: (($b["msgs"][count($b["msgs"]) - 1]["t"] ?? 0) <=> ($a["msgs"][count($a["msgs"]) - 1]["t"] ?? 0))
				?: strcmp($a["name"], $b["name"]);
		});
		return array_slice($list, 0, self::MAX_CONTACTS);
	}

	public static function markRead(Player $player, string $otherId) : void{
		$me = self::playerId($player);
		self::$seen[$me][$otherId] = time() * 1000;
	}

	public static function clearHistoryForMe(Player $player, string $otherId) : void{
		$me = self::playerId($player);
		self::$cleared[$me][$otherId] = time() * 1000;
	}

	public static function sendMessage(Player $player, string $otherId, string $text) : void{
		$me = self::playerId($player);
		$key = self::convKey($me, $otherId);
		self::$messages[$key][] = ["f" => $me, "t" => (int) (microtime(true) * 1000), "m" => $text];
		self::trimMessages($key);
		self::botReply($me, $otherId, $text);
	}

	public static function renderChat(Player $player, string $otherId, string $otherName) : string{
		$me = self::playerId($player);
		$msgs = self::conversationMessages($me, $otherId);
		if(count($msgs) === 0){
			return "No messages yet.\nType below and press Send.";
		}
		$lines = [];
		foreach(array_slice($msgs, -self::MAX_MESSAGES_SHOWN) as $m){
			$mine = $m["f"] === $me;
			$who = $mine ? "You" : $otherName;
			$lines[] = $who . " | " . self::formatTime($m["t"]) . "\n" . $m["m"] . "\n";
		}
		return implode("\n", $lines);
	}

	public static function contactToggleLabel(array $contact, bool $isOpen, int $index) : string{
		$marker = $contact["unread"] ? "> " : "";
		$name = $marker . $contact["name"];
		$status = $isOpen ? "Chatting" : "Tap to open";
		return "[" . $index . "] " . $name . "\n" . $status;
	}

	private static function convKey(string $a, string $b) : string{
		return $a < $b ? "$a:$b" : "$b:$a";
	}

	/**
	 * @return list<array{f: string, t: int, m: string}>
	 */
	private static function conversationMessages(string $me, string $otherId) : array{
		$key = self::convKey($me, $otherId);
		$clearedAt = self::$cleared[$me][$otherId] ?? 0;
		$all = self::$messages[$key] ?? [];
		$out = [];
		foreach($all as $m){
			if($m["t"] > $clearedAt){
				$out[] = $m;
			}
		}
		return $out;
	}

	private static function trimMessages(string $key) : void{
		$msgs = &self::$messages[$key];
		while(count($msgs) > self::MAX_MESSAGES_STORED){
			array_shift($msgs);
		}
	}

	private static function botReply(string $me, string $botId, string $text) : void{
		$lower = strtolower(trim($text));
		$reply = match(true){
			$lower === "help" => "Commands: help, long, clear. Anything else is echoed.",
			$lower === "long" => "Long test line for wrapping in the chat panel — repeated text filler to simulate PostSystem scroll behavior in DDUI.",
			$lower === "clear" => "(bot cleared shared log — use !clear in UI for local-only hide)",
			default => $text,
		};
		if($lower === "clear"){
			self::$messages[self::convKey($me, $botId)] = [];
			return;
		}
		$key = self::convKey($me, $botId);
		self::$messages[$key][] = ["f" => $botId, "t" => (int) (microtime(true) * 1000), "m" => $reply];
		self::trimMessages($key);
	}

	private static function formatTime(int $tsMs) : string{
		$sec = (int) ($tsMs / 1000);
		return date("H:i-d.m.Y", $sec);
	}

	private static function seedDemo(Player $player) : void{
		$me = self::playerId($player);
		if(isset(self::$seeded[$me])){
			return;
		}
		self::$seeded[$me] = true;
		$key = self::convKey($me, "bot_test");
		$t = (int) (microtime(true) * 1000);
		self::$messages[$key] = [
			["f" => "bot_test", "t" => $t - 60000, "m" => "Welcome to MineChat DDUI (PostSystem layout test)."],
			["f" => "bot_test", "t" => $t - 30000, "m" => "Type help for bot commands."],
		];
	}
}
