# Endurance Route

Immutable Route revisions, streamed content and optimistic publication for PHP 8.5.

```bash
composer require endurance/route
```

Supply a `RouteRepository`, caller-generated IDs and prepared content:

```php
use Endurance\Foundation\Clock\SystemClock;
use Endurance\Route\Application\UseCase\PublishRoute;

$publisher = new PublishRoute($repository, new SystemClock());
$route = $publisher->create($routeId, $revisionId, $metadata, $properties, $content);
$expected = $route->expectation();
```

- `revise()` publishes a new immutable content revision using both expected head
  fields. Metadata changes advance the card version without rewriting content.
  Keep an exact revision reference when reading retained history.
- Geometry, via points, waypoints and cues are separate streams. Via points do
  not imply geometry; coverage does not certify navigation. Anchors identify an
  exact occurrence within one revision, including repeated loop coordinates.
- Publication exhausts and validates every stream. Custom storage must implement
  the [atomic writer contract](src/Application/Port/RouteRevisionWriter.php),
  including private staging, globally unused revision IDs and cleanup that
  cannot delete committed content after an uncertain acknowledgement.
- Authorization, retention policy and source ownership belong to the application.
  Reconcile uncertain publication by its stable IDs before retrying.

[Executable example](examples/create-route.php) with a small in-memory adapter;
provide durable storage for persistence and concurrent writers.

License: [MPL-2.0](LICENSE).
