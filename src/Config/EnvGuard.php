<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Fails the process at start when required configuration is absent, instead of
 * letting the first request discover it. Matters under FrankenPHP worker mode,
 * where the process is long-lived.
 */
final class EnvGuard
{
    public const REQUIRED = ['SCOUT_API_TOKEN', 'GOOGLE_ROUTES_API_KEY'];

    /**
     * @param array<string, string|null> $env
     *
     * @return string[] names of variables that are absent, empty, or whitespace-only
     */
    public static function missing(array $env): array
    {
        $missing = [];

        foreach (self::REQUIRED as $name) {
            if ('' === trim((string) ($env[$name] ?? ''))) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /**
     * @param array<string, string|null> $env
     */
    public static function assert(array $env): void
    {
        $missing = self::missing($env);

        if ([] === $missing) {
            return;
        }

        fwrite(fopen('php://stderr', 'wb'), sprintf(
            "scout: missing required environment variable(s): %s\n",
            implode(', ', $missing),
        ));

        exit(1);
    }
}
