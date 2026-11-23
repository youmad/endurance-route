<?php

declare(strict_types=1);

namespace Endurance\Route\ValueObject;

use Endurance\Geo\PathPosition;

final readonly class GeometryAnchor
{
    public function __construct(public RouteRevisionId $revisionId, public PathPosition $position)
    {
    }
}
