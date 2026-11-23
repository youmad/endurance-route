<?php

declare(strict_types=1);

namespace Endurance\Route\ValueObject;

final readonly class RouteContentSummary
{
    public function __construct(
        public int $geometryPointCount,
        public int $geometryPartCount,
        public int $geometryElevationCount,
        public int $viaPointCount,
        public int $viaElevationCount,
        public int $waypointCount,
        public int $cueCount,
    ) {
        foreach (get_object_vars($this) as $count) {
            if ($count < 0) {
                throw new \InvalidArgumentException('Content counts must be nonnegative.');
            }
        }
        if ((0 === $geometryPointCount) !== (0 === $geometryPartCount)
            || $geometryPartCount > $geometryPointCount
            || $geometryElevationCount > $geometryPointCount
            || $viaElevationCount > $viaPointCount) {
            throw new \InvalidArgumentException('Content counts are inconsistent.');
        }
        if (0 === $geometryPointCount && 0 === $viaPointCount) {
            throw new \InvalidArgumentException('A route requires geometry or at least one via point.');
        }
    }

    public function geometryEdgeCount(): int
    {
        return $this->geometryPointCount - $this->geometryPartCount;
    }

    public function hasBreaks(): bool
    {
        return $this->geometryPartCount > 1;
    }
}
