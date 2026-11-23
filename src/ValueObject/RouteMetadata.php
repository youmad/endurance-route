<?php

declare(strict_types=1);

namespace Endurance\Route\ValueObject;

final readonly class RouteMetadata
{
    public function __construct(public ?string $name = null, public ?string $description = null)
    {
    }
}
