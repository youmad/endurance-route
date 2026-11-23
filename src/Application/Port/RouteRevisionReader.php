<?php

declare(strict_types=1);

namespace Endurance\Route\Application\Port;

use Endurance\Geo\PathGeometry;
use Endurance\Geo\ViaPoints;
use Endurance\Route\Entity\RouteRevision;
use Endurance\Route\Exception\RouteRevisionNotFound;
use Endurance\Route\ValueObject\NavigationCue;
use Endurance\Route\ValueObject\RouteRevisionId;
use Endurance\Route\ValueObject\RouteWaypoint;

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
