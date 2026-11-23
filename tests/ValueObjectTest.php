<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Foundation\ValueObject\Coordinate;
use Youmad\Endurance\Foundation\ValueObject\Instant;
use Youmad\Endurance\Route\Entity\Route;
use Youmad\Endurance\Route\Entity\RouteRevision;
use Youmad\Endurance\Route\ValueObject\NavigationCue;
use Youmad\Endurance\Route\ValueObject\ReportedDistance;
use Youmad\Endurance\Route\ValueObject\RevisionProperties;
use Youmad\Endurance\Route\ValueObject\RevisionProvenance;
use Youmad\Endurance\Route\ValueObject\RouteContentSummary;
use Youmad\Endurance\Route\ValueObject\RouteId;
use Youmad\Endurance\Route\ValueObject\RouteMetadata;
use Youmad\Endurance\Route\ValueObject\RouteRevisionId;
use Youmad\Endurance\Route\ValueObject\RouteVersion;
use Youmad\Endurance\Route\ValueObject\RouteWaypoint;
use Youmad\Endurance\Route\ValueObject\ViaPointAnchor;

final class ValueObjectTest extends TestCase
{
    public function testIdentifiersRoundTripAndRemainDifferentRoles(): void
    {
        $text = '019a04cd-72d1-7000-8000-abcdef123456';
        $route = RouteId::fromString(strtoupper($text));
        $revision = RouteRevisionId::fromString($text);
        self::assertSame($text, $route->toString());
        self::assertSame($text, $revision->toString());
        self::assertTrue($route->equals(RouteId::fromString($text)));
        self::assertTrue($revision->equals(RouteRevisionId::fromString(strtoupper($text))));
        self::assertNotSame($route::class, $revision::class);
        self::assertFalse(RouteId::generate()->equals($route));
        self::assertFalse(RouteRevisionId::generate()->equals($revision));
    }

    #[DataProvider('invalidValues')]
    public function testRejectsInvalidValues(string $kind): void
    {
        $this->expectException(\InvalidArgumentException::class);
        match ($kind) {
            'route UUID' => RouteId::fromString('not-a-uuid'),
            'revision UUID' => RouteRevisionId::fromString('not-a-uuid'),
            'version zero' => new RouteVersion(0),
            'version negative' => new RouteVersion(-1),
            'distance negative' => new ReportedDistance(-1.0),
            'distance NaN' => new ReportedDistance(NAN),
            'distance infinity' => new ReportedDistance(INF),
            'distance negative infinity' => new ReportedDistance(-INF),
            'origin blank' => new RevisionProvenance(' '),
            'source blank' => new RevisionProvenance(sourceReference: ''),
            'source version blank' => new RevisionProvenance(sourceRevision: ''),
            'interpretation blank' => new RevisionProvenance(interpretationVersion: ' '),
            'waypoint negative' => new RouteWaypoint(-1, new Coordinate(0.0, 0.0)),
            'cue negative' => new NavigationCue(-1, 'Turn'),
            'cue blank' => new NavigationCue(0, ' '),
            'via anchor negative' => new ViaPointAnchor(RouteRevisionId::generate(), -1),
            'summary empty' => new RouteContentSummary(0, 0, 0, 0, 0, 0, 0),
            'summary negative' => new RouteContentSummary(0, 0, 0, 1, 0, -1, 0),
            'summary missing part' => new RouteContentSummary(1, 0, 0, 0, 0, 0, 0),
            'summary empty part' => new RouteContentSummary(0, 1, 0, 1, 0, 0, 0),
            'summary too many parts' => new RouteContentSummary(1, 2, 0, 0, 0, 0, 0),
            'summary geometry heights' => new RouteContentSummary(1, 1, 2, 0, 0, 0, 0),
            'summary via heights' => new RouteContentSummary(0, 0, 0, 1, 2, 0, 0),
            default => throw new \LogicException('Unknown invalid-value fixture.'),
        };
    }

    /** @return iterable<array{string}> */
    public static function invalidValues(): iterable
    {
        foreach (['route UUID', 'revision UUID', 'version zero', 'version negative', 'distance negative', 'distance NaN', 'distance infinity', 'distance negative infinity', 'origin blank', 'source blank', 'source version blank', 'interpretation blank', 'waypoint negative', 'cue negative', 'cue blank', 'via anchor negative', 'summary empty', 'summary negative', 'summary missing part', 'summary empty part', 'summary too many parts', 'summary geometry heights', 'summary via heights'] as $kind) {
            yield $kind => [$kind];
        }
    }

    public function testRecordedZeroIsPreservedAndUnknownIsNotFabricated(): void
    {
        self::assertSame(0.0, (new ReportedDistance(0.0))->metres);
        $properties = new RevisionProperties();
        self::assertNull($properties->reportedDistance);
        self::assertSame('unknown', $properties->provenance->origin);
        self::assertNull($properties->provenance->sourceReference);
    }

    public function testVersionNeverWrapsAtTheIntegerBoundary(): void
    {
        $version = new RouteVersion(PHP_INT_MAX);
        $this->expectException(\OverflowException::class);
        $version->next();
    }

    public function testARevisionCannotBeInstalledOnAnotherRoute(): void
    {
        $now = Instant::fromDateTimeImmutable(new \DateTimeImmutable('2026-10-15T00:00:00Z'));
        $summary = new RouteContentSummary(0, 0, 0, 1, 0, 0, 0);
        $revision = new RouteRevision(RouteRevisionId::generate(), RouteId::generate(), $now, new RevisionProperties(), $summary);
        $route = Route::create($revision, new RouteMetadata());
        $other = new RouteRevision(RouteRevisionId::generate(), RouteId::generate(), $now, new RevisionProperties(), $summary);
        $this->expectException(\InvalidArgumentException::class);
        $route->withRevision($other, $route->expectation());
    }

    public function testReplacingWithTheCurrentIdIsRejected(): void
    {
        $now = Instant::fromDateTimeImmutable(new \DateTimeImmutable('2026-10-15T00:00:00Z'));
        $revision = new RouteRevision(RouteRevisionId::generate(), RouteId::generate(), $now, new RevisionProperties(), new RouteContentSummary(0, 0, 0, 1, 0, 0, 0));
        $route = Route::create($revision, new RouteMetadata());
        $this->expectException(\InvalidArgumentException::class);
        $route->withRevision($revision, $route->expectation());
    }
}
