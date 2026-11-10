<?php

declare(strict_types=1);

namespace pocketmine\player\camera;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\camera\CameraProgressOption;
use pocketmine\network\mcpe\protocol\types\camera\CameraRotationOption;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstructionEaseType;
use pocketmine\network\mcpe\protocol\types\camera\CameraSplineDefinition;
use pocketmine\network\mcpe\protocol\types\camera\CameraSplineInstruction;

/**
 * Fluent builder for {@link CameraSplineInstruction} / named {@link CameraSplineDefinition}.
 */
final class CameraSplineBuilder{

	private float $totalTime = 1.0;
	private CameraSetInstructionEaseType $easeType = CameraSetInstructionEaseType::LINEAR;
	/** @var Vector3[] */
	private array $curve = [];
	/** @var CameraProgressOption[] */
	private array $progressKeyFrames = [];
	/** @var CameraRotationOption[] */
	private array $rotationOptions = [];
	private string $splineIdentifier = "";
	private bool $loadFromJson = false;

	public static function create() : self{
		return new self();
	}

	public function time(float $seconds) : self{
		$this->totalTime = $seconds;
		return $this;
	}

	public function ease(CameraSetInstructionEaseType $ease) : self{
		$this->easeType = $ease;
		return $this;
	}

	public function point(Vector3 $point) : self{
		$this->curve[] = $point;
		return $this;
	}

	/**
	 * @param Vector3 ...$points
	 */
	public function points(Vector3 ...$points) : self{
		foreach($points as $point){
			$this->curve[] = $point;
		}
		return $this;
	}

	public function progress(CameraProgressOption $option) : self{
		$this->progressKeyFrames[] = $option;
		return $this;
	}

	public function rotation(CameraRotationOption $option) : self{
		$this->rotationOptions[] = $option;
		return $this;
	}

	public function id(string $identifier) : self{
		$this->splineIdentifier = $identifier;
		return $this;
	}

	public function loadFromJson(bool $load = true) : self{
		$this->loadFromJson = $load;
		return $this;
	}

	public function build() : CameraSplineInstruction{
		return new CameraSplineInstruction(
			$this->totalTime,
			$this->easeType,
			$this->curve,
			$this->progressKeyFrames,
			$this->rotationOptions,
			$this->splineIdentifier,
			$this->loadFromJson
		);
	}

	public function buildNamed(string $name) : CameraSplineDefinition{
		$instruction = $this->build();
		return new CameraSplineDefinition($name !== "" ? $name : ($this->splineIdentifier !== "" ? $this->splineIdentifier : "spline"), $instruction);
	}
}
