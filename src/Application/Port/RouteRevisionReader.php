<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\Application\Port;

use Youmad\Endurance\Geo\PathGeometry;
use Youmad\Endurance\Geo\ViaPoints;
use Youmad\Endurance\Route\Entity\RouteRevision;
use Youmad\Endurance\Route\Exception\RouteRevisionNotFound;
use Youmad\Endurance\Route\ValueObject\NavigationCue;
use Youmad\Endurance\Route\ValueObject\RouteRevisionId;
use Youmad\Endurance\Route\ValueObject\RouteWaypoint;

/**
 * Published data only. Each call opens a fresh stream pinned to this exact ID,
 * including across internal pages. Missing IDs throw RouteRevisionNotFound,
 * never silently return current content or an empty result. Callers own ACL.
 */
interface RouteRevisionReader
{
    /** @throws RouteRevisionNotFound */
    public function getRevision(RouteRevisionId $id): RouteRevision;

    public function geometry(RouteRevisionId $id): PathGeometry;

    public function viaPoints(RouteRevisionId $id): ViaPoints;

    /** @return iterable<RouteWaypoint> */
    public function waypoints(RouteRevisionId $id): iterable;

    /** @return iterable<NavigationCue> */
    public function cues(RouteRevisionId $id): iterable;
}
