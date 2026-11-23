<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\Application\UseCase;

use Youmad\Endurance\Foundation\Clock\Clock;
use Youmad\Endurance\Route\Application\Port\RouteRepository;
use Youmad\Endurance\Route\Entity\Route;
use Youmad\Endurance\Route\ValueObject\ExpectedRouteState;
use Youmad\Endurance\Route\ValueObject\RouteId;
use Youmad\Endurance\Route\ValueObject\RouteMetadata;

final readonly class EditRouteMetadata
{
    public function __construct(private RouteRepository $routes, private Clock $clock)
    {
    }

    public function edit(RouteId $id, ExpectedRouteState $expected, RouteMetadata $metadata): Route
    {
        $route = $this->routes->get($id)->withMetadata($metadata, $this->clock->now(), $expected);
        $this->routes->saveMetadata($route, $expected);

        return $route;
    }
}
