<?php

declare(strict_types=1);

namespace App\Provider;

final class RouteResult
{
    public function __construct(
        public readonly int $distanceMeters,
        public readonly int $durationSeconds,
    ) {
    }
}
