<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\ValueObject;

final readonly class ViaPointAnchor
{
    public function __construct(public RouteRevisionId $revisionId, public int $index)
    {
        if ($index < 0) {
            throw new \InvalidArgumentException('Via point anchor index must be nonnegative.');
        }
    }
}
