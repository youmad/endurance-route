<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\Exception;

final class RouteWriteFailure extends \RuntimeException
{
    public function __construct(\Throwable $failure, public readonly \Throwable $cleanupFailure)
    {
        parent::__construct('Route write failed and unpublished staging cleanup also failed.', 0, $failure);
    }
}
