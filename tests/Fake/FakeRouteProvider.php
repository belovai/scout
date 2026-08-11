<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Provider\RouteProvider;
use App\Provider\RouteResult;

final class FakeRouteProvider implements RouteProvider
{
    /** @var string[]|null */
    public ?array $lastPoints = null;

    public function __construct(
        private RouteResult|\Throwable $outcome = new RouteResult(12450, 1080),
    ) {
    }

    public function setOutcome(RouteResult|\Throwable $outcome): void
    {
        $this->outcome = $outcome;
    }

    public function computeRoute(array $points): RouteResult
    {
        $this->lastPoints = $points;

        if ($this->outcome instanceof \Throwable) {
            throw $this->outcome;
        }

        return $this->outcome;
    }

    public function name(): string
    {
        return 'fake';
    }
}
