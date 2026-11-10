<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

/** Admin-defined theme alias ({@code pocketmine.yml → ddui.themes}). */
final class DduiThemePreset{
	public function __construct(
		private string $alias,
		private string $styleId,
		private ?string $resourcePack,
	){
	}

	public function getAlias() : string{
		return $this->alias;
	}

	/** Empty = vanilla Ore UI default (no {@code styleId} on wire). */
	public function getStyleId() : string{
		return $this->styleId;
	}

	public function getResourcePack() : ?string{
		return $this->resourcePack;
	}
}
