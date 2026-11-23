<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\ValueObject;

final readonly class ExpectedRouteState
{
    public function __construct(public RouteVersion $version, public RouteRevisionId $revisionId)
    {
    }
}
