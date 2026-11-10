<?php

declare(strict_types=1);

namespace pocketmine\form\ddui;

/** Discovered {@code ddui/styling/*.json} style from a resource pack. */
final class DduiThemeStyle{
	public function __construct(
		private string $styleId,
		private string $packName,
		private string $sourcePath,
	){
	}

	public function getStyleId() : string{
		return $this->styleId;
	}

	public function getPackName() : string{
		return $this->packName;
	}

	public function getSourcePath() : string{
		return $this->sourcePath;
	}
}
