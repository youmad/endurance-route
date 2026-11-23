<?php

declare(strict_types=1);

namespace Endurance\Route\Entity;

use Endurance\Foundation\ValueObject\Instant;
use Endurance\Route\ValueObject\GeometryCoverage;
use Endurance\Route\ValueObject\RevisionProperties;
use Endurance\Route\ValueObject\RouteContentSummary;
use Endurance\Route\ValueObject\RouteId;
use Endurance\Route\ValueObject\RouteRevisionId;

final readonly class RouteRevision
{
    public function __construct(
        public RouteRevisionId $id,
        public RouteId $routeId,
        public Instant $createdAt,
        public RevisionProperties $properties,
        public RouteContentSummary $summary,
    ) {
        if ((GeometryCoverage::None === $properties->geometryCoverage) !== (0 === $summary->geometryPointCount)) {
            throw new \InvalidArgumentException('Geometry coverage must agree with the presence of geometry.');
        }
    }
}
