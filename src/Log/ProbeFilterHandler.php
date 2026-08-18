<?php

declare(strict_types=1);

namespace App\Log;

use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Drops the log lines the liveness/readiness probe generates, and passes
 * everything else to the wrapped handler.
 *
 * Symfony's RouterListener logs one `request.INFO: Matched route "healthz"`
 * line per probe hit. At two replicas and a ten-second period that is roughly
 * seventeen thousand lines a day, all of them identical, all of them hiding the
 * lines that matter. Filtering by channel or by level would take the real
 * request logging with it, so the filter is by path instead.
 *
 * Records at warning and above always pass, probe path or not: a probe that
 * fails is exactly the event worth keeping.
 */
final class ProbeFilterHandler implements HandlerInterface
{
    public function __construct(
        private readonly HandlerInterface $inner,
        private readonly string $probePath = '/healthz',
        private readonly Level $alwaysKeepFrom = Level::Warning,
    ) {
    }

    public function isHandling(LogRecord $record): bool
    {
        return $this->inner->isHandling($record);
    }

    public function handle(LogRecord $record): bool
    {
        // true means "handled, stop bubbling" -- for a dropped record that is
        // the point: no later handler should get a second chance to print it.
        return $this->isProbeNoise($record) ? true : $this->inner->handle($record);
    }

    /**
     * @param LogRecord[] $records
     */
    public function handleBatch(array $records): void
    {
        foreach ($records as $record) {
            $this->handle($record);
        }
    }

    public function close(): void
    {
        $this->inner->close();
    }

    private function isProbeNoise(LogRecord $record): bool
    {
        if ($record->level->value >= $this->alwaysKeepFrom->value) {
            return false;
        }

        $requestUri = $record->context['request_uri'] ?? null;

        if (!is_string($requestUri)) {
            return false;
        }

        return $this->probePath === parse_url($requestUri, PHP_URL_PATH);
    }
}
