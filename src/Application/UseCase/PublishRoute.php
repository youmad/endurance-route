<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\Application\UseCase;

use Youmad\Endurance\Foundation\Clock\Clock;
use Youmad\Endurance\Route\Application\Port\RouteRepository;
use Youmad\Endurance\Route\Application\RevisionContent;
use Youmad\Endurance\Route\Application\RouteContentValidator;
use Youmad\Endurance\Route\Entity\Route;
use Youmad\Endurance\Route\Entity\RouteRevision;
use Youmad\Endurance\Route\Exception\RouteWriteFailure;
use Youmad\Endurance\Route\ValueObject\ExpectedRouteState;
use Youmad\Endurance\Route\ValueObject\RevisionProperties;
use Youmad\Endurance\Route\ValueObject\RouteId;
use Youmad\Endurance\Route\ValueObject\RouteMetadata;
use Youmad\Endurance\Route\ValueObject\RouteRevisionId;

final readonly class PublishRoute
{
    public function __construct(private RouteRepository $routes, private Clock $clock)
    {
    }

    public function create(RouteId $id, RouteRevisionId $revisionId, RouteMetadata $metadata, RevisionProperties $properties, RevisionContent $content): Route
    {
        return $this->write($id, $revisionId, $metadata, $properties, $content);
    }

    public function revise(RouteId $id, ExpectedRouteState $expected, RouteRevisionId $revisionId, RevisionProperties $properties, RevisionContent $content): Route
    {
        $current = $this->routes->get($id);
        $current->assertExpected($expected);
        if ($current->currentRevisionId->equals($revisionId)) {
            throw new \InvalidArgumentException('Revision replacement requires a new ID.');
        }

        return $this->write($id, $revisionId, $current->metadata, $properties, $content, $current);
    }

    private function write(RouteId $id, RouteRevisionId $revisionId, RouteMetadata $metadata, RevisionProperties $properties, RevisionContent $content, ?Route $current = null): Route
    {
        $writer = $this->routes->beginRevision($id, $revisionId);
        try {
            $summary = (new RouteContentValidator())->write($revisionId, $content, $writer);
            $revision = new RouteRevision($revisionId, $id, $this->clock->now(), $properties, $summary);
            if (null === $current) {
                $route = Route::create($revision, $metadata);
                $writer->publishNew($route, $revision);
            } else {
                $expected = $current->expectation();
                $route = $current->withRevision($revision, $expected);
                $writer->publishReplacement($route, $revision, $expected);
            }

            return $route;
        } catch (\Throwable $failure) {
            try {
                $writer->discard();
            } catch (\Throwable $cleanupFailure) {
                throw new RouteWriteFailure($failure, $cleanupFailure);
            }
            throw $failure;
        }
    }
}
