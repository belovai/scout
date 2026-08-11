<?php

declare(strict_types=1);

namespace App\Tests\Config;

use App\Config\EnvGuard;
use PHPUnit\Framework\TestCase;

final class EnvGuardTest extends TestCase
{
    public function testNothingMissingWhenBothSet(): void
    {
        self::assertSame([], EnvGuard::missing([
            'SCOUT_API_TOKEN' => 'token',
            'GOOGLE_ROUTES_API_KEY' => 'key',
        ]));
    }

    public function testReportsAbsentVariables(): void
    {
        self::assertSame(
            ['SCOUT_API_TOKEN', 'GOOGLE_ROUTES_API_KEY'],
            EnvGuard::missing([]),
        );
    }

    public function testReportsEmptyAndWhitespaceOnlyVariables(): void
    {
        self::assertSame(
            ['SCOUT_API_TOKEN', 'GOOGLE_ROUTES_API_KEY'],
            EnvGuard::missing([
                'SCOUT_API_TOKEN' => '',
                'GOOGLE_ROUTES_API_KEY' => '   ',
            ]),
        );
    }

    public function testReportsOnlyTheMissingOne(): void
    {
        self::assertSame(
            ['GOOGLE_ROUTES_API_KEY'],
            EnvGuard::missing(['SCOUT_API_TOKEN' => 'token']),
        );
    }
}
