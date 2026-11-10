<?php

namespace pocketmine\world\worldedit\pattern\logic\math;

use pocketmine\world\worldedit\pattern\parser\WrongPatternUsageException;
use pocketmine\world\worldedit\pattern\Pattern;
use pocketmine\world\worldedit\pattern\type\AxisArgumentWrapper;
use pocketmine\world\worldedit\pattern\type\AxisPatternData;
use pocketmine\world\worldedit\selection\Selection;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;
use pocketmine\world\worldedit\world\ChunkController;

class DivisiblePattern extends Pattern
{
	use AxisPatternData {
		AxisPatternData::__construct as private __constructAxisPatternData;
		AxisPatternData::putData as private putAxisPatternData;
		AxisPatternData::parseData as private parseAxisPatternData;
	}

	/**
	 * @param AxisArgumentWrapper $axi
	 * @param int                 $divisor
	 * @param Pattern[]           $pieces
	 */
	public function __construct(AxisArgumentWrapper $axi, private int $divisor, array $pieces)
	{
		parent::__construct($pieces);
		$this->__constructAxisPatternData($axi, $pieces);

		if ($divisor <= 0) {
			throw new WrongPatternUsageException("Divisible needs a non-zero divisor");
		}
	}

	/**
	 * @param int             $x
	 * @param int             $y
	 * @param int             $z
	 * @param ChunkController $iterator
	 * @param Selection       $selection
	 * @return bool
	 */
	public function isValidAt(int $x, int $y, int $z, ChunkController $iterator, Selection $selection): bool
	{
		if ($this->xAxis && abs($x) % $this->divisor !== 0) {
			return false;
		}
		if ($this->yAxis && abs($y) % $this->divisor !== 0) {
			return false;
		}
		if ($this->zAxis && abs($z) % $this->divisor !== 0) {
			return false;
		}
		return true;
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		$stream->putInt($this->divisor);
		$this->putAxisPatternData($stream);
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		$this->divisor = $stream->getInt();
		$this->parseAxisPatternData($stream);
	}
}