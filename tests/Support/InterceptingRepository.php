<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\Tests\Support;

use Youmad\Endurance\Geo\PathPoint;
use Youmad\Endurance\Geo\PathPosition;
use Youmad\Endurance\Geo\ViaPoint;
use Youmad\Endurance\Route\Application\Port\RouteRepository;
use Youmad\Endurance\Route\Application\Port\RouteRevisionWriter;
use Youmad\Endurance\Route\Entity\Route;
use Youmad\Endurance\Route\Entity\RouteRevision;
use Youmad\Endurance\Route\ValueObject\ExpectedRouteState;
use Youmad\Endurance\Route\ValueObject\NavigationCue;
use Youmad\Endurance\Route\ValueObject\RouteId;
use Youmad\Endurance\Route\ValueObject\RouteRevisionId;
use Youmad\Endurance\Route\ValueObject\RouteWaypoint;

final class InterceptingRepository implements RouteRepository
{
    public ?\Closure $beforePublish = null;
    public ?\Throwable $appendFailure = null;
    public ?\Throwable $publishFailure = null;
    public ?\Throwable $discardFailure = null;
    public bool $loseAcknowledgement = false;
    public int $discards = 0;

    public function __construct(private readonly RouteRepository $inner)
    {
    }

    public function get(RouteId $id): Route
    {
        return $this->inner->get($id);
    }

    public function saveMetadata(Route $route, ExpectedRouteState $expected): void
    {
        $this->inner->saveMetadata($route, $expected);
    }

    public function beginRevision(RouteId $routeId, RouteRevisionId $revisionId): RouteRevisionWriter
    {
        return new class($this->inner->beginRevision($routeId, $revisionId), $this) implements RouteRevisionWriter {
            public function __construct(private readonly RouteRevisionWriter $inner, private readonly InterceptingRepository $hooks)
            {
            }

            public function appendGeometry(PathPoint $point): void
            {
                if (null !== $this->hooks->appendFailure) {
                    throw $this->hooks->appendFailure;
                }
                $this->inner->appendGeometry($point);
            }

            public function appendViaPoint(ViaPoint $point): void
            {
                $this->inner->appendViaPoint($point);
            }

            public function appendWaypoint(RouteWaypoint $waypoint): void
            {
                $this->inner->appendWaypoint($waypoint);
            }

            public function appendCue(NavigationCue $cue): void
            {
                $this->inner->appendCue($cue);
            }

            public function hasGeometryPosition(PathPosition $position): bool
            {
                return $this->inner->hasGeometryPosition($position);
            }

            public function publishNew(Route $route, RouteRevision $revision): void
            {
                $this->before();
                $this->inner->publishNew($route, $revision);
                $this->after();
            }

            public function publishReplacement(Route $route, RouteRevision $revision, ExpectedRouteState $expected): void
            {
                $this->before();
                $this->inner->publishReplacement($route, $revision, $expected);
                $this->after();
            }

            public function discard(): void
            {
                ++$this->hooks->discards;
                if (null !== $this->hooks->discardFailure) {
                    throw $this->hooks->discardFailure;
                }
                $this->inner->discard();
            }

            private function before(): void
            {
                if (null !== $this->hooks->beforePublish) {
                    ($this->hooks->beforePublish)();
                }
                if (null !== $this->hooks->publishFailure) {
                    throw $this->hooks->publishFailure;
                }
            }

            private function after(): void
            {
                if ($this->hooks->loseAcknowledgement) {
                    throw new \RuntimeException('Publication acknowledgement lost.');
                }
            }
        };
    }
}
