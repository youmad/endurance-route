<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\Application;

use Youmad\Endurance\Route\Application\Port\RouteRevisionWriter;
use Youmad\Endurance\Route\ValueObject\GeometryAnchor;
use Youmad\Endurance\Route\ValueObject\NavigationCue;
use Youmad\Endurance\Route\ValueObject\RouteContentSummary;
use Youmad\Endurance\Route\ValueObject\RouteRevisionId;
use Youmad\Endurance\Route\ValueObject\RouteWaypoint;
use Youmad\Endurance\Route\ValueObject\ViaPointAnchor;

/** Exhaust all inputs before publication; retain counts only, not a path-sized index. */
final class RouteContentValidator
{
    public function write(RouteRevisionId $id, RevisionContent $content, RouteRevisionWriter $writer): RouteContentSummary
    {
        $geometry = $parts = $geometryElevation = $via = $viaElevation = $waypoints = $cues = 0;
        foreach ($content->geometry as $point) {
            $geometry = $this->increment($geometry);
            $parts += 0 === $point->position->pointIndex ? 1 : 0;
            $geometryElevation += null !== $point->elevation ? 1 : 0;
            $writer->appendGeometry($point);
        }
        foreach ($content->viaPoints as $point) {
            $via = $this->increment($via);
            $viaElevation += null !== $point->elevation ? 1 : 0;
            $writer->appendViaPoint($point);
        }
        foreach ($content->waypoints as $waypoint) {
            if (!$waypoint instanceof RouteWaypoint || $waypoint->index !== $waypoints) {
                throw new \InvalidArgumentException('Waypoints must be RouteWaypoint values indexed consecutively from zero.');
            }
            $this->assertAnchor($waypoint->anchor, $id, $via, $writer);
            $waypoints = $this->increment($waypoints);
            $writer->appendWaypoint($waypoint);
        }
        foreach ($content->cues as $cue) {
            if (!$cue instanceof NavigationCue || $cue->index !== $cues) {
                throw new \InvalidArgumentException('Cues must be NavigationCue values indexed consecutively from zero.');
            }
            $this->assertAnchor($cue->anchor, $id, $via, $writer);
            $cues = $this->increment($cues);
            $writer->appendCue($cue);
        }

        return new RouteContentSummary($geometry, $parts, $geometryElevation, $via, $viaElevation, $waypoints, $cues);
    }

    private function assertAnchor(GeometryAnchor|ViaPointAnchor|null $anchor, RouteRevisionId $id, int $viaCount, RouteRevisionWriter $writer): void
    {
        if (null === $anchor) {
            return;
        }
        if (!$anchor->revisionId->equals($id)) {
            throw new \InvalidArgumentException('Anchor must refer to the revision being prepared.');
        }
        $exists = $anchor instanceof GeometryAnchor
            ? $writer->hasGeometryPosition($anchor->position)
            : $anchor->index < $viaCount;
        if (!$exists) {
            throw new \InvalidArgumentException('Anchor target does not exist in this revision.');
        }
    }

    private function increment(int $count): int
    {
        if (PHP_INT_MAX === $count) {
            throw new \OverflowException('Route content count exceeds the supported range.');
        }

        return $count + 1;
    }
}
