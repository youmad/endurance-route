<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\Entity;

use Youmad\Endurance\Foundation\ValueObject\Instant;
use Youmad\Endurance\Route\ValueObject\GeometryCoverage;
use Youmad\Endurance\Route\ValueObject\RevisionProperties;
use Youmad\Endurance\Route\ValueObject\RouteContentSummary;
use Youmad\Endurance\Route\ValueObject\RouteId;
use Youmad\Endurance\Route\ValueObject\RouteRevisionId;

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
