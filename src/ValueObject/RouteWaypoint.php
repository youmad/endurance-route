<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\ValueObject;

use Youmad\Endurance\Foundation\ValueObject\Coordinate;
use Youmad\Endurance\Geo\Elevation;

final readonly class RouteWaypoint
{
    public function __construct(
        public int $index,
        public Coordinate $coordinate,
        public ?string $name = null,
        public ?Elevation $elevation = null,
        public GeometryAnchor|ViaPointAnchor|null $anchor = null,
    ) {
        if ($index < 0) {
            throw new \InvalidArgumentException('Waypoint index must be nonnegative.');
        }
    }
}
