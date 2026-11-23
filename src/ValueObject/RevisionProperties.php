<?php

declare(strict_types=1);

namespace Endurance\Route\ValueObject;

final readonly class RevisionProperties
{
    public function __construct(
        public GeometryCoverage $geometryCoverage = GeometryCoverage::None,
        public ?ReportedDistance $reportedDistance = null,
        public RevisionProvenance $provenance = new RevisionProvenance(),
    ) {
    }
}
