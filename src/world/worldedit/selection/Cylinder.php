<?php

namespace pocketmine\world\worldedit\selection;

use Closure;
use Generator;
use pocketmine\world\worldedit\math\OffGridBlockVector;
use pocketmine\world\worldedit\selection\constructor\CylindricalConstructor;
use pocketmine\world\worldedit\selection\constructor\ShapeConstructor;
use pocketmine\world\worldedit\selection\constructor\SingleBlockConstructor;
use pocketmine\world\worldedit\selection\constructor\TubeConstructor;
use pocketmine\world\worldedit\selection\cubic\CubicChunkLoader;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

class Cylinder extends Selection
{
	use CubicChunkLoader;

	/**
	 * @param string             $world
	 * @param OffGridBlockVector $point
	 * @param float              $radius
	 * @param int                $height
	 */
	public function __construct(string $world, private OffGridBlockVector $point, private float $radius, private int $height)
	{
		parent::__construct($world, $point->add((int) -ceil($radius), 0, (int) -ceil($radius))->forceIntoGrid(), $point->add((int) ceil($radius), $height - 1, (int) ceil($radius))->forceIntoGrid());
	}

	/**
	 * @param Closure          $closure
	 * @param SelectionContext $context
	 * @return Generator<ShapeConstructor>
	 */
	public function asShapeConstructors(Closure $closure, SelectionContext $context): Generator
	{
		if ($context->isEmpty()) {
			return;
		}

		if ($context->isFull()) {
			yield new CylindricalConstructor($closure, $this->getPoint(), $this->getRadius(), $this->getHeight());
		} else {
			if ($context->includesFilling()) {
				yield new CylindricalConstructor($closure, $this->getPoint(), $this->getRadius() - 1, $this->getHeight());
			}

			if ($context->includesAllSides()) {
				yield from CylindricalConstructor::hollowAround($this->getPoint(), $this->getRadius(), $context->getSideThickness(), $this->getHeight(), $closure);
			} elseif ($context->includesWalls()) {
				yield new TubeConstructor($closure, $this->getPoint(), $this->getRadius(), $this->getHeight(), $context->getSideThickness());
			}

			if ($context->includesCenter() && $this->getPoint()->isInGrid()) {
				yield new SingleBlockConstructor($closure, $this->getPoint()->forceIntoGrid());
			}
		}
	}

	/**
	 * @param OffGridBlockVector $point
	 */
	public function setPoint(OffGridBlockVector $point): void
	{
		$this->point = $point;
	}

	/**
	 * @return OffGridBlockVector
	 */
	public function getPoint(): OffGridBlockVector
	{
		return $this->point;
	}

	/**
	 * @param float $radius
	 */
	public function setRadius(float $radius): void
	{
		$this->radius = $radius;
	}

	/**
	 * @return float
	 */
	public function getRadius(): float
	{
		return $this->radius;
	}

	/**
	 * @param int $height
	 */
	public function setHeight(int $height): void
	{
		$this->height = $height;
	}

	/**
	 * @return int
	 */
	public function getHeight(): int
	{
		return $this->height;
	}

	/**
	 * @param ExtendedBinaryStream $stream
	 */
	public function putData(ExtendedBinaryStream $stream): void
	{
		parent::putData($stream);

		$stream->putBlockVector($this->point);
		$stream->putFloat($this->radius);
		$stream->putInt($this->height);
	}

	/**
	 * @param ExtendedBinaryStream $stream
	 */
	public function parseData(ExtendedBinaryStream $stream): void
	{
		parent::parseData($stream);

		$this->point = $stream->getOffGridBlockVector();
		$this->radius = $stream->getFloat();
		$this->height = $stream->getInt();
	}
}