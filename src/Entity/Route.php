<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\Entity;

use Youmad\Endurance\Foundation\ValueObject\Instant;
use Youmad\Endurance\Route\Exception\RouteConflict;
use Youmad\Endurance\Route\ValueObject\ExpectedRouteState;
use Youmad\Endurance\Route\ValueObject\RouteId;
use Youmad\Endurance\Route\ValueObject\RouteMetadata;
use Youmad\Endurance\Route\ValueObject\RouteRevisionId;
use Youmad\Endurance\Route\ValueObject\RouteVersion;

final readonly class Route
{
    public function __construct(
        public RouteId $id,
        public RouteMetadata $metadata,
        public Instant $createdAt,
        public Instant $updatedAt,
        public RouteRevisionId $currentRevisionId,
        public RouteVersion $version,
    ) {
        if ($updatedAt->isBefore($createdAt)) {
            throw new \InvalidArgumentException('Route update cannot predate creation.');
        }
    }

    public static function create(RouteRevision $revision, RouteMetadata $metadata): self
    {
        return new self($revision->routeId, $metadata, $revision->createdAt, $revision->createdAt, $revision->id, new RouteVersion(1));
    }

    public function expectation(): ExpectedRouteState
    {
        return new ExpectedRouteState($this->version, $this->currentRevisionId);
    }

    public function assertExpected(ExpectedRouteState $expected): void
    {
        if (!$this->version->equals($expected->version) || !$this->currentRevisionId->equals($expected->revisionId)) {
            throw new RouteConflict('Route changed since the expected state was read.');
        }
    }

    public function withMetadata(RouteMetadata $metadata, Instant $changedAt, ExpectedRouteState $expected): self
    {
        $this->assertExpected($expected);

        return new self($this->id, $metadata, $this->createdAt, $changedAt, $this->currentRevisionId, $this->version->next());
    }

    public function withRevision(RouteRevision $revision, ExpectedRouteState $expected): self
    {
        $this->assertExpected($expected);
        if (!$revision->routeId->equals($this->id) || $revision->id->equals($this->currentRevisionId)) {
            throw new \InvalidArgumentException('Replacement must be a new revision of the same route.');
        }

        return new self($this->id, $this->metadata, $this->createdAt, $revision->createdAt, $revision->id, $this->version->next());
    }
}
