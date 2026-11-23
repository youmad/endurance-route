<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\Application\Port;

use Youmad\Endurance\Route\Entity\Route;
use Youmad\Endurance\Route\Exception\RouteConflict;
use Youmad\Endurance\Route\Exception\RouteNotFound;
use Youmad\Endurance\Route\Exception\RouteRevisionAlreadyExists;
use Youmad\Endurance\Route\ValueObject\ExpectedRouteState;
use Youmad\Endurance\Route\ValueObject\RouteId;
use Youmad\Endurance\Route\ValueObject\RouteRevisionId;

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
