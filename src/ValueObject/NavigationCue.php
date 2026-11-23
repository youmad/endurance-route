<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\ValueObject;

use Youmad\Endurance\Foundation\ValueObject\Coordinate;

final readonly class NavigationCue
{
    public function __construct(
        public int $index,
        public string $instruction,
        public ?Coordinate $coordinate = null,
        public GeometryAnchor|ViaPointAnchor|null $anchor = null,
    ) {
        if ($index < 0 || '' === trim($instruction)) {
            throw new \InvalidArgumentException('Cue requires a nonnegative index and a nonblank instruction.');
        }
    }
}
