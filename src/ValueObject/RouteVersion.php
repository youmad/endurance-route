<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\ValueObject;

final readonly class RouteVersion
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new \InvalidArgumentException('Route version must be positive.');
        }
    }

    public function next(): self
    {
        if (PHP_INT_MAX === $this->value) {
            throw new \OverflowException('Route version exceeds the supported range.');
        }

        return new self($this->value + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
