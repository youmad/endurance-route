<?php

declare(strict_types=1);

namespace Endurance\Route\Application\Port;

use Endurance\Route\Entity\Route;
use Endurance\Route\Exception\RouteConflict;
use Endurance\Route\Exception\RouteNotFound;
use Endurance\Route\Exception\RouteRevisionAlreadyExists;
use Endurance\Route\ValueObject\ExpectedRouteState;
use Endurance\Route\ValueObject\RouteId;
use Endurance\Route\ValueObject\RouteRevisionId;

interface RouteRepository
{
    /** @throws RouteNotFound */
    public function get(RouteId $id): Route;

    /**
     * Reserve a globally unique revision ID for invisible, privately owned staging.
     * Never edit an existing revision, even when it is no longer current.
     *
     * @throws RouteRevisionAlreadyExists
     */
    public function beginRevision(RouteId $routeId, RouteRevisionId $revisionId): RouteRevisionWriter;

    /**
     * Atomically compare BOTH expected fields and save the next card version.
     * Never replace revision content here. Failure leaves the card unchanged.
     *
     * @throws RouteConflict
     * @throws RouteNotFound
     */
    public function saveMetadata(Route $route, ExpectedRouteState $expected): void;
}
