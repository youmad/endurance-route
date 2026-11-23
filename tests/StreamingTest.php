<?php

declare(strict_types=1);

namespace Endurance\Route\Tests;

use Endurance\Foundation\Clock\FrozenClock;
use Endurance\Foundation\ValueObject\Coordinate;
use Endurance\Foundation\ValueObject\Instant;
use Endurance\Geo\PathGeometry;
use Endurance\Geo\PathPoint;
use Endurance\Geo\PathPosition;
use Endurance\Geo\ViaPoint;
use Endurance\Route\Application\Port\RouteRepository;
use Endurance\Route\Application\Port\RouteRevisionWriter;
use Endurance\Route\Application\RevisionContent;
use Endurance\Route\Application\UseCase\PublishRoute;
use Endurance\Route\Entity\Route;
use Endurance\Route\Entity\RouteRevision;
use Endurance\Route\Exception\RouteNotFound;
use Endurance\Route\ValueObject\ExpectedRouteState;
use Endurance\Route\ValueObject\GeometryCoverage;
use Endurance\Route\ValueObject\NavigationCue;
use Endurance\Route\ValueObject\RevisionProperties;
use Endurance\Route\ValueObject\RouteId;
use Endurance\Route\ValueObject\RouteMetadata;
use Endurance\Route\ValueObject\RouteRevisionId;
use Endurance\Route\ValueObject\RouteWaypoint;
use PHPUnit\Framework\TestCase;

final class StreamingTest extends TestCase
{
    public function testPublicationConsumesAWholeLargeSourceWithBoundedWorkingMemory(): void
    {
        // A counting sink excludes storage and mock invocation logs from the memory measurement.
        $writer = new class implements RouteRevisionWriter {
            public int $points = 0;
            public bool $published = false;

            public function appendGeometry(PathPoint $point): void
            {
                ++$this->points;
            }

            public function appendViaPoint(ViaPoint $point): void
            {
            }

            public function appendWaypoint(RouteWaypoint $waypoint): void
            {
            }

            public function appendCue(NavigationCue $cue): void
            {
            }

            public function hasGeometryPosition(PathPosition $position): bool
            {
                throw new \LogicException('This fixture does not test anchors.');
            }

            public function publishNew(Route $route, RouteRevision $revision): void
            {
                self::verifyCount($this->points, $revision);
                $this->published = true;
            }

            public function publishReplacement(Route $route, RouteRevision $revision, ExpectedRouteState $expected): void
            {
                throw new \LogicException('This fixture tests creation only.');
            }

            public function discard(): void
            {
            }

            private static function verifyCount(int $points, RouteRevision $revision): void
            {
                if (300_001 !== $points || $revision->summary->geometryPointCount !== $points) {
                    throw new \RuntimeException('Publication happened before the source was exhausted.');
                }
            }
        };
        $repository = new class($writer) implements RouteRepository {
            public function __construct(private readonly RouteRevisionWriter $writer)
            {
            }

            public function get(RouteId $id): Route
            {
                throw new RouteNotFound();
            }

            public function beginRevision(RouteId $routeId, RouteRevisionId $revisionId): RouteRevisionWriter
            {
                return $this->writer;
            }

            public function saveMetadata(Route $route, ExpectedRouteState $expected): void
            {
                throw new \LogicException('This fixture tests creation only.');
            }
        };
        $baseline = memory_get_usage();
        $maxGrowth = 0;
        $source = static function () use ($baseline, &$maxGrowth): \Generator {
            for ($i = 0; $i < 300_001; ++$i) {
                if (0 === $i % 1000) {
                    $maxGrowth = max($maxGrowth, memory_get_usage() - $baseline);
                }
                yield new PathPoint(new PathPosition(intdiv($i, 50_000), $i % 50_000), new Coordinate(0.0, 0.0));
            }
        };
        $clock = new FrozenClock(Instant::fromDateTimeImmutable(new \DateTimeImmutable('2026-10-15T00:00:00Z')));
        $route = (new PublishRoute($repository, $clock))->create(RouteId::generate(), RouteRevisionId::generate(), new RouteMetadata(), new RevisionProperties(GeometryCoverage::Partial), new RevisionContent(new PathGeometry($source())));
        self::assertTrue($writer->published);
        self::assertSame(300_001, $writer->points);
        self::assertLessThan(2 * 1024 * 1024, $maxGrowth);
        self::assertSame(1, $route->version->value);
    }
}
