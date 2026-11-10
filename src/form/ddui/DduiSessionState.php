<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

enum DduiSessionState{
	case OPENING;
	case OPEN;
	case CLOSED;
}
