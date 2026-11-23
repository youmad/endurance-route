<?php

declare(strict_types=1);

namespace Endurance\Route\Examples\Support;

use Endurance\Geo\PathGeometry;
use Endurance\Geo\PathPoint;
use Endurance\Geo\ViaPoint;
use Endurance\Geo\ViaPoints;
use Endurance\Route\Application\Port\RouteRepository;
use Endurance\Route\Application\Port\RouteRevisionReader;
use Endurance\Route\Application\Port\RouteRevisionWriter;
use Endurance\Route\Entity\Route;
use Endurance\Route\Entity\RouteRevision;
use Endurance\Route\Exception\RouteAlreadyExists;
use Endurance\Route\Exception\RouteNotFound;
use Endurance\Route\Exception\RouteRevisionAlreadyExists;
use Endurance\Route\Exception\RouteRevisionNotFound;
use Endurance\Route\ValueObject\ExpectedRouteState;
use Endurance\Route\ValueObject\NavigationCue;
use Endurance\Route\ValueObject\RouteId;
use Endurance\Route\ValueObject\RouteRevisionId;
use Endurance\Route\ValueObject\RouteWaypoint;

require_once __DIR__.'/InMemoryRevisionWriter.php';

/**
 * In-memory example for small inputs; no persistence or concurrency guarantees.
 *
 * @phpstan-type Content array{geometry: list<PathPoint>, via: list<ViaPoint>, waypoints: list<RouteWaypoint>, cues: list<NavigationCue>}
 */
final class InMemoryRouteStore implements RouteRepository, RouteRevisionReader
{
    /** @var array<string, Route> */
    private array $routes = [];
    /** @var array<string, RouteRevision> */
    private array $revisions = [];
    /** @var array<string, Content> */
    private array $contents = [];
    /** @var array<string, true> */
    private array $reservations = [];

    public function get(RouteId $id): Route
    {
        return $this->routes[$id->toString()] ?? throw new RouteNotFound($id->toString());
    }

    public function getRevision(RouteRevisionId $id): RouteRevision
    {
        return $this->revisions[$id->toString()] ?? throw new RouteRevisionNotFound($id->toString());
    }

    public function beginRevision(RouteId $routeId, RouteRevisionId $revisionId): RouteRevisionWriter
    {
        $key = $revisionId->toString();
        if (isset($this->revisions[$key]) || isset($this->reservations[$key])) {
            throw new RouteRevisionAlreadyExists($key);
        }
        $this->reservations[$key] = true;

        return new InMemoryRevisionWriter(
            $routeId,
            $revisionId,
            /** @param Content $content */
            function (Route $route, RouteRevision $revision, ?ExpectedRouteState $expected, array $content) use ($routeId, $key): void {
                if (null === $expected) {
                    if (isset($this->routes[$routeId->toString()])) {
                        throw new RouteAlreadyExists($routeId->toString());
                    }
                } else {
                    $this->get($routeId)->assertExpected($expected);
                }

                $this->contents[$key] = $content;
                $this->revisions[$key] = $revision;
                $this->routes[$routeId->toString()] = $route;
                unset($this->reservations[$key]);
            },
            function () use ($key): void {
                unset($this->reservations[$key]);
            },
        );
    }

    public function saveMetadata(Route $route, ExpectedRouteState $expected): void
    {
        $current = $this->get($route->id);
        $current->assertExpected($expected);
        if (!$route->version->equals($expected->version->next())
            || !$route->currentRevisionId->equals($expected->revisionId)
            || !$route->createdAt->equals($current->createdAt)) {
            throw new \InvalidArgumentException('Metadata update must preserve revision and creation identity.');
        }
        $this->routes[$route->id->toString()] = $route;
    }

    public function geometry(RouteRevisionId $id): PathGeometry
    {
        return new PathGeometry($this->content($id)['geometry']);
    }

    public function viaPoints(RouteRevisionId $id): ViaPoints
    {
        return new ViaPoints($this->content($id)['via']);
    }

    public function waypoints(RouteRevisionId $id): iterable
    {
        yield from $this->content($id)['waypoints'];
    }

    public function cues(RouteRevisionId $id): iterable
    {
        yield from $this->content($id)['cues'];
    }

    /** @return Content */
    private function content(RouteRevisionId $id): array
    {
        $this->getRevision($id);

        return $this->contents[$id->toString()];
    }
}
