<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\Application;

use Youmad\Endurance\Geo\PathGeometry;
use Youmad\Endurance\Geo\ViaPoints;

/** Streams may be single-pass; annotations are validated only when consumed. */
final readonly class RevisionContent
{
    /**
     * @param iterable<mixed> $waypoints
     * @param iterable<mixed> $cues
     */
    public function __construct(
        public PathGeometry $geometry = new PathGeometry([]),
        public ViaPoints $viaPoints = new ViaPoints([]),
        public iterable $waypoints = [],
        public iterable $cues = [],
    ) {
    }
}
