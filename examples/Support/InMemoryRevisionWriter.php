<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\Examples\Support;

use Youmad\Endurance\Geo\PathPoint;
use Youmad\Endurance\Geo\PathPosition;
use Youmad\Endurance\Geo\ViaPoint;
use Youmad\Endurance\Route\Application\Port\RouteRevisionWriter;
use Youmad\Endurance\Route\Entity\Route;
use Youmad\Endurance\Route\Entity\RouteRevision;
use Youmad\Endurance\Route\ValueObject\ExpectedRouteState;
use Youmad\Endurance\Route\ValueObject\NavigationCue;
use Youmad\Endurance\Route\ValueObject\RouteContentSummary;
use Youmad\Endurance\Route\ValueObject\RouteId;
use Youmad\Endurance\Route\ValueObject\RouteRevisionId;
use Youmad\Endurance\Route\ValueObject\RouteWaypoint;

/** @phpstan-import-type Content from InMemoryRouteStore */
final class InMemoryRevisionWriter implements RouteRevisionWriter
{
    /** @var list<PathPoint> */
    private array $geometry = [];
    /** @var list<ViaPoint> */
    private array $via = [];
    /** @var list<RouteWaypoint> */
    private array $waypoints = [];
    /** @var list<NavigationCue> */
    private array $cues = [];
    private bool $closed = false;

    /** @param \Closure(Route, RouteRevision, ?ExpectedRouteState, Content): void $publish */
    public function __construct(
        private readonly RouteId $routeId,
        private readonly RouteRevisionId $revisionId,
        private readonly \Closure $publish,
        private readonly \Closure $release,
    ) {
    }

    public function appendGeometry(PathPoint $point): void
    {
        $this->assertOpen();
        $this->geometry[] = $point;
    }

    public function appendViaPoint(ViaPoint $point): void
    {
        $this->assertOpen();
        $this->via[] = $point;
    }

    public function appendWaypoint(RouteWaypoint $waypoint): void
    {
        $this->assertOpen();
        $this->waypoints[] = $waypoint;
    }

    public function appendCue(NavigationCue $cue): void
    {
        $this->assertOpen();
        $this->cues[] = $cue;
    }

    public function hasGeometryPosition(PathPosition $position): bool
    {
        $this->assertOpen();

        return array_any($this->geometry, static fn (PathPoint $point): bool => $point->position == $position);
    }

    public function publishNew(Route $route, RouteRevision $revision): void
    {
        if (1 !== $route->version->value) {
            throw new \InvalidArgumentException('A new route starts at version one.');
        }
        $this->commit($route, $revision, null);
    }

    public function publishReplacement(Route $route, RouteRevision $revision, ExpectedRouteState $expected): void
    {
        if (!$route->version->equals($expected->version->next())) {
            throw new \InvalidArgumentException('Replacement must advance the card version.');
        }
        $this->commit($route, $revision, $expected);
    }

    public function discard(): void
    {
        if (!$this->closed) {
            ($this->release)();
            $this->closed = true;
            $this->clear();
        }
    }

    private function commit(Route $route, RouteRevision $revision, ?ExpectedRouteState $expected): void
    {
        $this->assertOpen();
        if (!$route->id->equals($this->routeId) || !$revision->routeId->equals($this->routeId)
            || !$revision->id->equals($this->revisionId) || !$route->currentRevisionId->equals($this->revisionId)) {
            throw new \InvalidArgumentException('Publication must match the writer reservation.');
        }
        $summary = new RouteContentSummary(
            count($this->geometry),
            count(array_filter($this->geometry, static fn (PathPoint $p): bool => 0 === $p->position->pointIndex)),
            count(array_filter($this->geometry, static fn (PathPoint $p): bool => null !== $p->elevation)),
            count($this->via),
            count(array_filter($this->via, static fn (ViaPoint $p): bool => null !== $p->elevation)),
            count($this->waypoints),
            count($this->cues),
        );
        if ($summary != $revision->summary) {
            throw new \InvalidArgumentException('Descriptor counts must match staged content.');
        }
        ($this->publish)($route, $revision, $expected, ['geometry' => $this->geometry, 'via' => $this->via, 'waypoints' => $this->waypoints, 'cues' => $this->cues]);
        $this->closed = true;
        $this->clear();
    }

    private function clear(): void
    {
        $this->geometry = $this->via = $this->waypoints = $this->cues = [];
    }

    private function assertOpen(): void
    {
        if ($this->closed) {
            throw new \LogicException('Revision writer is already closed.');
        }
    }
}
