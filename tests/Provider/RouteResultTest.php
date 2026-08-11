<?php

declare(strict_types=1);

namespace App\Tests\Provider;

use App\Provider\Exception\NoRouteException;
use App\Provider\Exception\ProviderException;
use App\Provider\RouteResult;
use PHPUnit\Framework\TestCase;

final class RouteResultTest extends TestCase
{
    public function testHoldsDistanceAndDuration(): void
    {
        $result = new RouteResult(12450, 1080);

        self::assertSame(12450, $result->distanceMeters);
        self::assertSame(1080, $result->durationSeconds);
    }

    public function testExceptionsAreRuntimeExceptions(): void
    {
        self::assertInstanceOf(\RuntimeException::class, new NoRouteException('none'));
        self::assertInstanceOf(\RuntimeException::class, new ProviderException('boom'));
    }
}
