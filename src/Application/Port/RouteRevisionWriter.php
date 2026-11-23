<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\Application\Port;

use Youmad\Endurance\Geo\PathPoint;
use Youmad\Endurance\Geo\PathPosition;
use Youmad\Endurance\Geo\ViaPoint;
use Youmad\Endurance\Route\Entity\Route;
use Youmad\Endurance\Route\Entity\RouteRevision;
use Youmad\Endurance\Route\ValueObject\ExpectedRouteState;
use Youmad\Endurance\Route\ValueObject\NavigationCue;
use Youmad\Endurance\Route\ValueObject\RouteWaypoint;

/**
 * One revision reservation. Appends copy values into staging, not references to
 * a caller's stream. Adapters may flush batches without exposing staged data.
 * No whole-path transaction or whole-path in-memory index is required.
 */
interface RouteRevisionWriter
{
    public function appendGeometry(PathPoint $point): void;

    public function appendViaPoint(ViaPoint $point): void;

    public function appendWaypoint(RouteWaypoint $waypoint): void;

    public function appendCue(NavigationCue $cue): void;

    /** Lookup only this writer's staged geometry, including previously flushed batches. */
    public function hasGeometryPosition(PathPosition $position): bool;

    /**
     * Atomically publish the matching descriptor, staged content and a NEW route.
     * Reject existing route/revision IDs; seal content permanently on success.
     * No partial card/revision may become visible if publication fails.
     */
    public function publishNew(Route $route, RouteRevision $revision): void;

    /**
     * Atomically check BOTH expected fields, publish content and switch the card.
     * Reject a stale state with RouteConflict. Preserve every historical revision.
     * Descriptor/card must match this reservation and the validated content.
     */
    public function publishReplacement(Route $route, RouteRevision $revision, ExpectedRouteState $expected): void;

    /**
     * Idempotently discard only this reservation's unpublished data. A successful
     * or ambiguously acknowledged publication must NEVER be deleted by cleanup.
     * Subsequent appends/publication after success or discard must be rejected.
     */
    public function discard(): void;
}
