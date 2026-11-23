<?php

declare(strict_types=1);

namespace Endurance\Route\ValueObject;

final readonly class ReportedDistance
{
    public function __construct(public float $metres)
    {
        if (!is_finite($metres) || $metres < 0.0) {
            throw new \InvalidArgumentException('Reported distance must be finite and nonnegative.');
        }
    }
}
