<?php

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\data\bedrock\item\SavedItemData;

/**
 * Holds unrecognized item type data so it can be round-tripped through save without loss.
 * Network display may still show as info_update; stacking is disabled to avoid merging different unknowns.
 */
final class UnknownItem extends Item{
	private static ?int $sharedTypeId = null;

	public function __construct(
		private SavedItemData $savedTypeData
	){
		self::$sharedTypeId ??= ItemTypeIds::newId();
		parent::__construct(new ItemIdentifier(self::$sharedTypeId), $savedTypeData->getName());
	}

	public function getSavedTypeData() : SavedItemData{
		return $this->savedTypeData;
	}

	public function getMaxStackSize() : int{
		return 1;
	}

	public static function typeId() : int{
		self::$sharedTypeId ??= ItemTypeIds::newId();
		return self::$sharedTypeId;
	}
}
