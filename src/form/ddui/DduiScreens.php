<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

final class DduiScreens{
	public const MESSAGE_BOX = "minecraft:message_box";
	public const CUSTOM_FORM = "minecraft:custom_form";

	/** MineChat PostSystem — requires RP screen `minechat:postsystem_chat` (see docs/MINECHAT_DDUI_POSTSYSTEM_SCREEN.md). */
	public const MINECHAT_POSTSYSTEM_CHAT = "minechat:postsystem_chat";

	private function __construct(){
	}
}
