<?php

namespace pocketmine\world\worldedit\selection;

use Closure;
use Generator;
use pocketmine\world\worldedit\math\BlockOffsetVector;
use pocketmine\world\worldedit\math\OffGridBlockVector;
use pocketmine\world\worldedit\selection\constructor\HollowSphericalConstructor;
use pocketmine\world\worldedit\selection\constructor\ShapeConstructor;
use pocketmine\world\worldedit\selection\constructor\SingleBlockConstructor;
use pocketmine\world\worldedit\selection\constructor\SphericalConstructor;
use pocketmine\world\worldedit\selection\constructor\TubeConstructor;
use pocketmine\world\worldedit\selection\cubic\CubicChunkLoader;
use pocketmine\world\worldedit\utils\ExtendedBinaryStream;

class Sphere extends Selection
{
	use CubicChunkLoader;

	/**
	 * @param string             $world
	 * @param OffGridBlockVector $point
	 * @param float              $radius
	 */
	public function __construct(string $world, private OffGridBlockVector $point, private float $radius)
	{
		parent::__construct($world, $point->offset(new BlockOffsetVector((int) -ceil($radius), (int) -ceil($radius), (int) -ceil($radius)))->forceIntoGrid(), $point->offset(new BlockOffsetVector((int) ceil($radius), (int) ceil($radius), (int) ceil($radius)))->forceIntoGrid());
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
			yield new SphericalConstructor($closure, $this->getPoint(), $this->getRadius());
		} else {
			if ($context->includesFilling()) {
				yield new SphericalConstructor($closure, $this->getPoint(), $this->getRadius() - 1);
			}

			if ($context->includesAllSides()) {
				yield new HollowSphericalConstructor($closure, $this->getPoint(), $this->getRadius(), $context->getSideThickness());
			} elseif ($context->includesWalls()) {
				yield new TubeConstructor($closure, $this->getPoint()->down((int) $this->getRadius()), $this->getRadius(), (int) $this->getRadius() * 2 + 1, $context->getSideThickness());
			}

			if ($context->includesCenter() && $this->point->isInGrid()) {
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
	 * @param ExtendedBinaryStream $stream
	 */
	public function putData(ExtendedBinaryStream $stream): void
	{
		parent::putData($stream);

		$stream->putBlockVector($this->point);
		$stream->putFloat($this->radius);
	}

	/**
	 * @param ExtendedBinaryStream $stream
	 */
	public function parseData(ExtendedBinaryStream $stream): void
	{
		parent::parseData($stream);

		$this->point = $stream->getOffGridBlockVector();
		$this->radius = $stream->getFloat();
	}
}