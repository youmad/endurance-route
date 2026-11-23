<?php

declare(strict_types=1);

use Youmad\Endurance\Foundation\Clock\FrozenClock;
use Youmad\Endurance\Foundation\ValueObject\Coordinate;
use Youmad\Endurance\Foundation\ValueObject\Instant;
use Youmad\Endurance\Geo\PathGeometry;
use Youmad\Endurance\Geo\PathLength;
use Youmad\Endurance\Geo\PathPoint;
use Youmad\Endurance\Geo\PathPosition;
use Youmad\Endurance\Route\Application\RevisionContent;
use Youmad\Endurance\Route\Application\UseCase\EditRouteMetadata;
use Youmad\Endurance\Route\Application\UseCase\PublishRoute;
use Youmad\Endurance\Route\Examples\Support\InMemoryRouteStore;
use Youmad\Endurance\Route\ValueObject\GeometryCoverage;
use Youmad\Endurance\Route\ValueObject\RevisionProperties;
use Youmad\Endurance\Route\ValueObject\RouteId;
use Youmad\Endurance\Route\ValueObject\RouteMetadata;
use Youmad\Endurance\Route\ValueObject\RouteRevisionId;

require $argv[1] ?? dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/Support/InMemoryRouteStore.php';

$store = new InMemoryRouteStore();
$clock = new FrozenClock(Instant::fromDateTimeImmutable(new DateTimeImmutable('2026-10-15T00:00:00Z')));
$publisher = new PublishRoute($store, $clock);
$route = $publisher->create(
    RouteId::generate(),
    RouteRevisionId::generate(),
    new RouteMetadata('Island crossing'),
    new RevisionProperties(GeometryCoverage::Complete),
    new RevisionContent(new PathGeometry((static function (): Generator {
        yield new PathPoint(new PathPosition(0, 0), new Coordinate(0.0, 179.0));
        yield new PathPoint(new PathPosition(0, 1), new Coordinate(0.0, -179.0));
    })())),
);
$edited = (new EditRouteMetadata($store, $clock))->edit($route->id, $route->expectation(), new RouteMetadata('Across the island'));
$length = new PathLength();
$length->append($store->geometry($route->currentRevisionId));
if (2 !== $edited->version->value || !$edited->currentRevisionId->equals($route->currentRevisionId)
    || abs(($length->summary()->knownLengthMetres ?? 0.0) - 222_390.1604670658) > 0.000001) {
    throw new RuntimeException('Route consumer produced an unexpected result.');
}
printf("Route version %d; revision unchanged by rename; %.3f known metres.\n", $edited->version->value, $length->summary()->knownLengthMetres);
