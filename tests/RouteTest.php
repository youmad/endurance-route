<?php

declare(strict_types=1);

namespace Endurance\Route\Tests;

use Endurance\Foundation\Clock\FrozenClock;
use Endurance\Foundation\ValueObject\Coordinate;
use Endurance\Foundation\ValueObject\Instant;
use Endurance\Geo\Elevation;
use Endurance\Geo\ElevationOrigin;
use Endurance\Geo\PathGeometry;
use Endurance\Geo\PathLength;
use Endurance\Geo\PathPoint;
use Endurance\Geo\PathPosition;
use Endurance\Geo\ViaPoint;
use Endurance\Geo\ViaPoints;
use Endurance\Route\Application\RevisionContent;
use Endurance\Route\Application\UseCase\EditRouteMetadata;
use Endurance\Route\Application\UseCase\PublishRoute;
use Endurance\Route\Entity\Route;
use Endurance\Route\Examples\Support\InMemoryRouteStore;
use Endurance\Route\Exception\RouteAlreadyExists;
use Endurance\Route\Exception\RouteConflict;
use Endurance\Route\Exception\RouteNotFound;
use Endurance\Route\Exception\RouteRevisionAlreadyExists;
use Endurance\Route\Exception\RouteRevisionNotFound;
use Endurance\Route\Exception\RouteWriteFailure;
use Endurance\Route\Tests\Support\InterceptingRepository;
use Endurance\Route\ValueObject\ExpectedRouteState;
use Endurance\Route\ValueObject\GeometryAnchor;
use Endurance\Route\ValueObject\GeometryCoverage;
use Endurance\Route\ValueObject\NavigationCue;
use Endurance\Route\ValueObject\ReportedDistance;
use Endurance\Route\ValueObject\RevisionProperties;
use Endurance\Route\ValueObject\RevisionProvenance;
use Endurance\Route\ValueObject\RouteId;
use Endurance\Route\ValueObject\RouteMetadata;
use Endurance\Route\ValueObject\RouteRevisionId;
use Endurance\Route\ValueObject\RouteWaypoint;
use Endurance\Route\ValueObject\ViaPointAnchor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__).'/examples/Support/InMemoryRouteStore.php';
require_once __DIR__.'/Support/InterceptingRepository.php';

final class RouteTest extends TestCase
{
    private InMemoryRouteStore $store;
    private PublishRoute $publisher;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->store = new InMemoryRouteStore();
        $this->clock = new FrozenClock(self::instant());
        $this->publisher = new PublishRoute($this->store, $this->clock);
    }

    public function testCreatesUntitledRouteWithoutAnActivityOrPointTimestamps(): void
    {
        $route = $this->create();
        $revision = $this->store->getRevision($route->currentRevisionId);
        self::assertNull($route->metadata->name);
        self::assertSame(1, $route->version->value);
        self::assertTrue($route->createdAt->equals(self::instant()));
        self::assertTrue($route->id->equals($revision->routeId));
        self::assertSame(1, $revision->summary->geometryPointCount);
        self::assertSame(0, $revision->summary->geometryEdgeCount());
        self::assertSame(0, $revision->summary->geometryElevationCount);
        self::assertNull(iterator_to_array($this->store->geometry($revision->id))[0]->elevation);
        self::assertNull($revision->properties->reportedDistance);
    }

    public function testViaOnlyRouteDoesNotAcquireAConnectingPath(): void
    {
        $points = [new ViaPoint(0, new Coordinate(0.0, 0.0)), new ViaPoint(1, new Coordinate(0.0, 90.0))];
        $route = $this->publisher->create(RouteId::generate(), RouteRevisionId::generate(), new RouteMetadata(), new RevisionProperties(), new RevisionContent(viaPoints: new ViaPoints($points)));
        $revision = $this->store->getRevision($route->currentRevisionId);
        self::assertSame(0, $revision->summary->geometryPointCount);
        self::assertSame(2, $revision->summary->viaPointCount);
        self::assertSame($points, iterator_to_array($this->store->viaPoints($revision->id)));
        $length = new PathLength();
        $length->append($this->store->geometry($revision->id));
        self::assertNull($length->summary()->knownLengthMetres);
        self::assertSame(GeometryCoverage::None, $revision->properties->geometryCoverage);
    }

    public function testKeepsBreaksElevationAndReportedDistanceSeparateFromComputedLength(): void
    {
        $elevation = new Elevation(-12.5, ElevationOrigin::Reported, 'device', 'EGM96');
        $points = [new PathPoint(new PathPosition(0, 0), new Coordinate(0.0, 0.0), $elevation), self::point(1, 0, 90.0)];
        $properties = new RevisionProperties(GeometryCoverage::Partial, new ReportedDistance(12500.0), new RevisionProvenance('recording', 'source:123', 'generation:2', 'projection-v1'));
        $route = $this->publisher->create(RouteId::generate(), RouteRevisionId::generate(), new RouteMetadata(), $properties, new RevisionContent(new PathGeometry($points), new ViaPoints([new ViaPoint(0, new Coordinate(5.0, 10.0), new Elevation(0.0))])));
        $revision = $this->store->getRevision($route->currentRevisionId);
        self::assertSame(2, $revision->summary->geometryPartCount);
        self::assertTrue($revision->summary->hasBreaks());
        self::assertSame(0, $revision->summary->geometryEdgeCount());
        self::assertSame(1, $revision->summary->geometryElevationCount);
        self::assertSame(1, $revision->summary->viaElevationCount);
        self::assertSame($elevation, iterator_to_array($this->store->geometry($revision->id))[0]->elevation);
        self::assertSame('generation:2', $revision->properties->provenance->sourceRevision);
        self::assertSame('projection-v1', $revision->properties->provenance->interpretationVersion);
        $length = new PathLength();
        $length->append($this->store->geometry($revision->id));
        self::assertNull($length->summary()->knownLengthMetres);
        self::assertSame(12500.0, $revision->properties->reportedDistance?->metres);
        self::assertSame($route, $this->store->get($route->id));
    }

    public function testMetadataEditPreservesRevisionAndHistoricalDescriptor(): void
    {
        $route = $this->create();
        $revision = $this->store->getRevision($route->currentRevisionId);
        $later = new FrozenClock(self::instant('2026-10-16T00:00:00Z'));
        $changed = (new EditRouteMetadata($this->store, $later))->edit($route->id, $route->expectation(), new RouteMetadata('New name', 'New description'));
        self::assertSame(2, $changed->version->value);
        self::assertTrue($route->currentRevisionId->equals($changed->currentRevisionId));
        self::assertSame($revision, $this->store->getRevision($changed->currentRevisionId));
        self::assertTrue($changed->createdAt->equals($route->createdAt));
        self::assertTrue($changed->updatedAt->equals($later->now()));
        self::assertNull($route->metadata->name);
        self::assertSame('New name', $changed->metadata->name);
    }

    public function testRevisionPreservesOldContentAndRequiresNewCuesAndBindings(): void
    {
        $route = $this->create(new RevisionContent(new PathGeometry([self::point(0, 0), self::point(0, 1, 1.0)])));
        $old = iterator_to_array($this->store->geometry($route->currentRevisionId));
        $newId = RouteRevisionId::generate();
        $replacement = new RevisionContent(new PathGeometry([self::point(0, 0, 1.0), self::point(0, 1)]), cues: [new NavigationCue(0, 'Turn right', anchor: new GeometryAnchor($newId, new PathPosition(0, 0)))]);
        $changed = $this->publisher->revise($route->id, $route->expectation(), $newId, self::properties(), $replacement);
        self::assertSame(2, $changed->version->value);
        self::assertTrue($changed->currentRevisionId->equals($newId));
        self::assertSame($old, iterator_to_array($this->store->geometry($route->currentRevisionId)));
        self::assertSame(1.0, iterator_to_array($this->store->geometry($newId))[0]->coordinate->longitude);
        self::assertSame('Turn right', iterator_to_array($this->store->cues($newId))[0]->instruction);
        self::assertSame([], iterator_to_array($this->store->cues($route->currentRevisionId)));
    }

    public function testLoopAnchorsSelectTheExactPassageAndAllowUnboundPois(): void
    {
        $id = RouteRevisionId::generate();
        $points = [self::point(0, 0), self::point(0, 1, 1.0), self::point(0, 2)];
        $waypoints = [new RouteWaypoint(0, new Coordinate(1.0, 1.0), 'Cafe', anchor: new GeometryAnchor($id, new PathPosition(0, 2))), new RouteWaypoint(1, new Coordinate(5.0, 5.0), 'Unlocated POI')];
        $cues = [new NavigationCue(0, 'Start', anchor: new GeometryAnchor($id, new PathPosition(0, 0))), new NavigationCue(1, 'Finish', anchor: new GeometryAnchor($id, new PathPosition(0, 2)))];
        $route = $this->publisher->create(RouteId::generate(), $id, new RouteMetadata(), self::properties(), new RevisionContent(new PathGeometry($points), waypoints: $waypoints, cues: $cues));
        self::assertSame($waypoints, iterator_to_array($this->store->waypoints($id)));
        self::assertSame($cues, iterator_to_array($this->store->cues($id)));
        self::assertSame(2, $this->store->getRevision($route->currentRevisionId)->summary->cueCount);
        self::assertNull(iterator_to_array($this->store->waypoints($id))[1]->anchor);
    }

    public function testViaAnchorWorksWithoutDetailedGeometry(): void
    {
        $id = RouteRevisionId::generate();
        $content = new RevisionContent(viaPoints: new ViaPoints([new ViaPoint(0, new Coordinate(1.0, 1.0))]), cues: [new NavigationCue(0, 'Stop here', anchor: new ViaPointAnchor($id, 0))]);
        $route = $this->publisher->create(RouteId::generate(), $id, new RouteMetadata(), new RevisionProperties(), $content);
        self::assertSame(1, $this->store->getRevision($route->currentRevisionId)->summary->cueCount);
    }

    #[DataProvider('badContentKinds')]
    public function testInvalidContentIsNeverPublished(string $kind): void
    {
        $routeId = RouteId::generate();
        $id = RouteRevisionId::generate();
        $content = match ($kind) {
            'empty' => new RevisionContent(),
            'poi only' => new RevisionContent(waypoints: [new RouteWaypoint(0, new Coordinate(0.0, 0.0))]),
            'geometry gap' => new RevisionContent(new PathGeometry([self::point(0, 0), self::point(0, 2)])),
            'wrong revision' => new RevisionContent(new PathGeometry([self::point(0, 0)]), cues: [new NavigationCue(0, 'Stop', anchor: new GeometryAnchor(RouteRevisionId::generate(), new PathPosition(0, 0)))]),
            'missing point' => new RevisionContent(new PathGeometry([self::point(0, 0)]), waypoints: [new RouteWaypoint(0, new Coordinate(0.0, 0.0), anchor: new GeometryAnchor($id, new PathPosition(0, 1)))]),
            'missing part' => new RevisionContent(new PathGeometry([self::point(0, 0)]), cues: [new NavigationCue(0, 'Stop', anchor: new GeometryAnchor($id, new PathPosition(1, 0)))]),
            'missing via' => new RevisionContent(new PathGeometry([self::point(0, 0)]), cues: [new NavigationCue(0, 'Stop', anchor: new ViaPointAnchor($id, 0))]),
            'via end index' => new RevisionContent(viaPoints: new ViaPoints([new ViaPoint(0, new Coordinate(0.0, 0.0))]), cues: [new NavigationCue(0, 'Stop', anchor: new ViaPointAnchor($id, 1))]),
            'waypoint gap' => new RevisionContent(new PathGeometry([self::point(0, 0)]), waypoints: [new RouteWaypoint(1, new Coordinate(0.0, 0.0))]),
            'cue gap' => new RevisionContent(new PathGeometry([self::point(0, 0)]), cues: [new NavigationCue(1, 'Stop')]),
            'waypoint type' => new RevisionContent(new PathGeometry([self::point(0, 0)]), waypoints: ['invalid']),
            'cue type' => new RevisionContent(new PathGeometry([self::point(0, 0)]), cues: ['invalid']),
            'coverage mismatch' => new RevisionContent(viaPoints: new ViaPoints([new ViaPoint(0, new Coordinate(0.0, 0.0))])),
            default => throw new \LogicException('Unknown invalid-content fixture.'),
        };
        try {
            $this->publisher->create($routeId, $id, new RouteMetadata(), self::properties(), $content);
            self::fail('Invalid content must fail.');
        } catch (\InvalidArgumentException) {
            $this->assertRevisionMissing($id);
            try {
                $this->store->get($routeId);
                self::fail('Invalid route must not become visible.');
            } catch (RouteNotFound) {
                $route = $this->publisher->create($routeId, $id, new RouteMetadata(), self::properties(), self::content());
                self::assertTrue($route->currentRevisionId->equals($id));
            }
        }
    }

    /** @return iterable<array{string}> */
    public static function badContentKinds(): iterable
    {
        foreach (['empty', 'poi only', 'geometry gap', 'wrong revision', 'missing point', 'missing part', 'missing via', 'via end index', 'waypoint gap', 'cue gap', 'waypoint type', 'cue type', 'coverage mismatch'] as $kind) {
            yield $kind => [$kind];
        }
    }

    public function testStagingIsInvisibleAndLateReaderFailurePreservesCurrentRevision(): void
    {
        $route = $this->create();
        $id = RouteRevisionId::generate();
        $source = function () use ($id, $route): \Generator {
            yield self::point(0, 0);
            self::assertSame($route, $this->store->get($route->id));
            $this->assertRevisionMissing($id);
            throw new \UnexpectedValueException('Late source failure.');
        };
        try {
            $this->publisher->revise($route->id, $route->expectation(), $id, self::properties(), new RevisionContent(new PathGeometry($source())));
            self::fail('Late input error must fail publication.');
        } catch (\UnexpectedValueException $failure) {
            self::assertSame('Late source failure.', $failure->getMessage());
            self::assertSame($route, $this->store->get($route->id));
            $this->assertRevisionMissing($id);
        }
    }

    public function testStaleStateRejectsBeforeReadingInput(): void
    {
        $route = $this->create();
        (new EditRouteMetadata($this->store, $this->clock))->edit($route->id, $route->expectation(), new RouteMetadata('Changed'));
        $read = false;
        $source = static function () use (&$read): \Generator {
            $read = true;
            yield self::point(0, 0);
        };
        try {
            $this->publisher->revise($route->id, $route->expectation(), RouteRevisionId::generate(), self::properties(), new RevisionContent(new PathGeometry($source())));
            self::fail('Stale state must conflict.');
        } catch (RouteConflict) {
            self::assertFalse($read);
        }
    }

    public function testLateCueFailureAlsoPreventsPublication(): void
    {
        $id = RouteRevisionId::generate();
        $cues = static function (): \Generator {
            yield new NavigationCue(0, 'Last visible instruction');
            throw new \UnexpectedValueException('Invalid source tail.');
        };
        try {
            $this->publisher->create(RouteId::generate(), $id, new RouteMetadata(), self::properties(), new RevisionContent(new PathGeometry([self::point(0, 0)]), cues: $cues()));
            self::fail('The end of every stream must be validated.');
        } catch (\UnexpectedValueException $failure) {
            self::assertSame('Invalid source tail.', $failure->getMessage());
            $this->assertRevisionMissing($id);
        }
    }

    public function testWrongExpectedRevisionConflictsEvenWithTheCorrectVersion(): void
    {
        $route = $this->create();
        $expected = new ExpectedRouteState($route->version, RouteRevisionId::generate());
        $this->expectException(RouteConflict::class);
        (new EditRouteMetadata($this->store, $this->clock))->edit($route->id, $expected, new RouteMetadata('Invalid'));
    }

    public function testMetadataRaceDuringStagingCannotBeOverwritten(): void
    {
        $route = $this->create();
        $id = RouteRevisionId::generate();
        $hooks = new InterceptingRepository($this->store);
        $hooks->beforePublish = function () use ($route): void {
            (new EditRouteMetadata($this->store, $this->clock))->edit($route->id, $route->expectation(), new RouteMetadata('Concurrent name'));
        };
        try {
            (new PublishRoute($hooks, $this->clock))->revise($route->id, $route->expectation(), $id, self::properties(), self::content());
            self::fail('Publication must check state again atomically.');
        } catch (RouteConflict) {
            self::assertSame('Concurrent name', $this->store->get($route->id)->metadata->name);
            self::assertTrue($this->store->get($route->id)->currentRevisionId->equals($route->currentRevisionId));
            self::assertSame(1, $hooks->discards);
            $this->assertRevisionMissing($id);
        }
    }

    public function testStaleMetadataSaveCannotReplaceANewerRevision(): void
    {
        $route = $this->create();
        $stale = $route->withMetadata(new RouteMetadata('Stale'), $this->clock->now(), $route->expectation());
        $this->publisher->revise($route->id, $route->expectation(), RouteRevisionId::generate(), self::properties(), self::content());
        $this->expectException(RouteConflict::class);
        $this->store->saveMetadata($stale, $route->expectation());
    }

    public function testCompetingRevisionWinsWithoutPublishingTheLosingDraft(): void
    {
        $route = $this->create();
        $winner = RouteRevisionId::generate();
        $loser = RouteRevisionId::generate();
        $hooks = new InterceptingRepository($this->store);
        $hooks->beforePublish = function () use ($route, $winner): void {
            $this->publisher->revise($route->id, $route->expectation(), $winner, self::properties(), self::content());
        };
        try {
            (new PublishRoute($hooks, $this->clock))->revise($route->id, $route->expectation(), $loser, self::properties(), self::content());
            self::fail('A stale competing revision must not be published.');
        } catch (RouteConflict) {
            self::assertTrue($this->store->get($route->id)->currentRevisionId->equals($winner));
            self::assertSame(2, $this->store->get($route->id)->version->value);
            self::assertCount(1, iterator_to_array($this->store->geometry($route->currentRevisionId)));
            self::assertCount(1, iterator_to_array($this->store->geometry($winner)));
            $this->assertRevisionMissing($loser);
        }
    }

    public function testPublishedValuesAreIndependentOfTheOriginalIterator(): void
    {
        $first = self::point(0, 0, 1.0);
        $source = new \ArrayIterator([$first]);
        $route = $this->create(new RevisionContent(new PathGeometry($source)));
        $source[0] = self::point(0, 0, 99.0);
        self::assertSame([$first], iterator_to_array($this->store->geometry($route->currentRevisionId)));
        self::assertSame([$first], iterator_to_array($this->store->geometry($route->currentRevisionId)));
    }

    public function testPublishedRevisionIdCannotBeReusedAfterItStopsBeingCurrent(): void
    {
        $route = $this->create();
        $changed = $this->publisher->revise($route->id, $route->expectation(), RouteRevisionId::generate(), self::properties(), self::content());
        $this->expectException(RouteRevisionAlreadyExists::class);
        $this->publisher->revise($changed->id, $changed->expectation(), $route->currentRevisionId, self::properties(), self::content());
    }

    public function testDuplicateRouteDoesNotAlterPublishedContent(): void
    {
        $route = $this->create();
        $id = RouteRevisionId::generate();
        try {
            $this->publisher->create($route->id, $id, new RouteMetadata('Duplicate'), self::properties(), self::content());
            self::fail('Existing route must not be replaced by create.');
        } catch (RouteAlreadyExists) {
            self::assertSame($route, $this->store->get($route->id));
            $this->assertRevisionMissing($id);
        }
    }

    public function testLostPublicationAcknowledgementCannotDeleteCommittedContent(): void
    {
        $hooks = new InterceptingRepository($this->store);
        $hooks->loseAcknowledgement = true;
        $routeId = RouteId::generate();
        $id = RouteRevisionId::generate();
        try {
            (new PublishRoute($hooks, $this->clock))->create($routeId, $id, new RouteMetadata(), self::properties(), self::content());
            self::fail('Acknowledgement loss must be surfaced.');
        } catch (\RuntimeException $failure) {
            self::assertSame('Publication acknowledgement lost.', $failure->getMessage());
            self::assertSame(1, $hooks->discards);
            self::assertTrue($this->store->get($routeId)->currentRevisionId->equals($id));
            self::assertCount(1, iterator_to_array($this->store->geometry($id)));
        }
    }

    #[DataProvider('writeFailureKinds')]
    public function testWriterFailureCleansUnpublishedStaging(string $kind): void
    {
        $hooks = new InterceptingRepository($this->store);
        $failure = new \RuntimeException('Storage failed.');
        if ('append' === $kind) {
            $hooks->appendFailure = $failure;
        } else {
            $hooks->publishFailure = $failure;
        }
        $id = RouteRevisionId::generate();
        try {
            (new PublishRoute($hooks, $this->clock))->create(RouteId::generate(), $id, new RouteMetadata(), self::properties(), self::content());
            self::fail('Storage failure must be surfaced.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
            self::assertSame(1, $hooks->discards);
            $this->assertRevisionMissing($id);
        }
    }

    /** @return iterable<array{string}> */
    public static function writeFailureKinds(): iterable
    {
        yield ['append'];
        yield ['publish'];
    }

    public function testCleanupFailurePreservesBothCauses(): void
    {
        $hooks = new InterceptingRepository($this->store);
        $hooks->appendFailure = new \RuntimeException('Write failed.');
        $hooks->discardFailure = new \RuntimeException('Cleanup failed.');
        try {
            (new PublishRoute($hooks, $this->clock))->create(RouteId::generate(), RouteRevisionId::generate(), new RouteMetadata(), self::properties(), self::content());
            self::fail('Both failures must be surfaced.');
        } catch (RouteWriteFailure $failure) {
            self::assertSame($hooks->appendFailure, $failure->getPrevious());
            self::assertSame($hooks->discardFailure, $failure->cleanupFailure);
        }
    }

    public function testMissingHistoricalRevisionNeverFallsBackToTheCurrentOne(): void
    {
        $this->create();
        $id = RouteRevisionId::generate();
        $this->assertRevisionMissing($id);
        $this->expectException(RouteRevisionNotFound::class);
        iterator_to_array($this->store->geometry($id));
    }

    public function testInvalidCoverageAfterSuccessfulStagingDiscardsTheReservation(): void
    {
        $id = RouteRevisionId::generate();
        try {
            $this->publisher->create(RouteId::generate(), $id, new RouteMetadata(), new RevisionProperties(), self::content());
            self::fail('Geometry cannot claim none coverage.');
        } catch (\InvalidArgumentException) {
            $this->assertRevisionMissing($id);
            $writer = $this->store->beginRevision(RouteId::generate(), $id);
            $writer->discard();
            $writer->discard();
            $this->expectException(\LogicException::class);
            $writer->appendGeometry(self::point(0, 0));
        }
    }

    private function create(?RevisionContent $content = null): Route
    {
        return $this->publisher->create(RouteId::generate(), RouteRevisionId::generate(), new RouteMetadata(), self::properties(), $content ?? self::content());
    }

    private function assertRevisionMissing(RouteRevisionId $id): void
    {
        try {
            $this->store->getRevision($id);
        } catch (RouteRevisionNotFound) {
            return;
        }
        self::fail('Unpublished revision must not be readable.');
    }

    private static function content(): RevisionContent
    {
        return new RevisionContent(new PathGeometry([self::point(0, 0)]));
    }

    private static function properties(): RevisionProperties
    {
        return new RevisionProperties(GeometryCoverage::Unknown);
    }

    private static function instant(string $date = '2026-10-15T00:00:00Z'): Instant
    {
        return Instant::fromDateTimeImmutable(new \DateTimeImmutable($date));
    }

    private static function point(int $part, int $point, float $longitude = 0.0): PathPoint
    {
        return new PathPoint(new PathPosition($part, $point), new Coordinate(0.0, $longitude));
    }
}
