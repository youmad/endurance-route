<?php

declare(strict_types=1);

namespace Endurance\Route\Application\UseCase;

use Endurance\Foundation\Clock\Clock;
use Endurance\Route\Application\Port\RouteRepository;
use Endurance\Route\Entity\Route;
use Endurance\Route\ValueObject\ExpectedRouteState;
use Endurance\Route\ValueObject\RouteId;
use Endurance\Route\ValueObject\RouteMetadata;

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
