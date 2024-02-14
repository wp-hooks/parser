<?php

namespace WP_Parser;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\Location;

class Hook {
	private string $name;

	private ?DocBlock $docBlock;

	private string $type;

	private array $args;

	private Location $location;

	private Location $endLocation;

	public function __construct(
		string $name,
		?DocBlock $docBlock,
		string $type,
		array $args,
		Location $location,
		Location $endLocation
	) {
		$this->name        = $name;
		$this->docBlock    = $docBlock;
		$this->type        = $type;
		$this->args        = $args;
		$this->location    = $location;
		$this->endLocation = $endLocation;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getDocBlock(): ?DocBlock {
		return $this->docBlock;
	}

	public function getType(): string {
		return $this->type;
	}

	public function getArgs(): array {
		return $this->args;
	}

	public function getLocation(): Location {
		return $this->location;
	}

	public function getEndLocation(): Location {
		return $this->endLocation;
	}
}
