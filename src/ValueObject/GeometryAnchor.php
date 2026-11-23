<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\ValueObject;

use Youmad\Endurance\Geo\PathPosition;

final readonly class GeometryAnchor
{
    public function __construct(public RouteRevisionId $revisionId, public PathPosition $position)
    {
    }
}
