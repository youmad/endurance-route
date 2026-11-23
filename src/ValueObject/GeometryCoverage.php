<?php

declare(strict_types=1);

namespace Endurance\Route\ValueObject;

/** Source coverage, independently of continuity and fitness for navigation. */
enum GeometryCoverage: string
{
    case None = 'none';
    case Unknown = 'unknown';
    case Partial = 'partial';
    case Complete = 'complete';
}
