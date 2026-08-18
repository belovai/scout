<?php

declare(strict_types=1);

namespace App\Tests\Log;

use App\Log\ProbeFilterHandler;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class ProbeFilterHandlerTest extends TestCase
{
    private TestHandler $inner;
    private ProbeFilterHandler $handler;

    protected function setUp(): void
    {
        $this->inner = new TestHandler();
        $this->handler = new ProbeFilterHandler($this->inner, '/healthz');
    }

    public function testDropsInfoRecordFromTheProbePath(): void
    {
        $this->handler->handle($this->record(Level::Info, 'http://10.42.3.32:8080/healthz'));

        self::assertSame([], $this->inner->getRecords());
    }

    public function testDroppedRecordStopsBubbling(): void
    {
        $handled = $this->handler->handle($this->record(Level::Info, 'http://10.42.3.32:8080/healthz'));

        self::assertTrue($handled, 'a dropped record must not fall through to another handler');
    }

    public function testKeepsInfoRecordFromAnyOtherPath(): void
    {
        $this->handler->handle($this->record(Level::Info, 'https://scout.h.belovai.com/route'));

        self::assertCount(1, $this->inner->getRecords());
    }

    public function testKeepsWarningAndAboveEvenOnTheProbePath(): void
    {
        $this->handler->handle($this->record(Level::Warning, 'http://127.0.0.1:8080/healthz'));
        $this->handler->handle($this->record(Level::Error, 'http://127.0.0.1:8080/healthz'));

        self::assertCount(2, $this->inner->getRecords());
    }

    public function testKeepsRecordWithoutRequestUriContext(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'provider call finished',
        );

        $this->handler->handle($record);

        self::assertCount(1, $this->inner->getRecords());
    }

    public function testIgnoresTheQueryStringWhenMatchingThePath(): void
    {
        $this->handler->handle($this->record(Level::Info, 'http://127.0.0.1:8080/healthz?probe=kubelet'));

        self::assertSame([], $this->inner->getRecords());
    }

    public function testDoesNotMatchAPathThatMerelyContainsTheProbePath(): void
    {
        $this->handler->handle($this->record(Level::Info, 'https://scout.h.belovai.com/healthz-report'));

        self::assertCount(1, $this->inner->getRecords());
    }

    public function testHandleBatchFiltersEachRecord(): void
    {
        $this->handler->handleBatch([
            $this->record(Level::Info, 'http://127.0.0.1:8080/healthz'),
            $this->record(Level::Info, 'https://scout.h.belovai.com/route'),
        ]);

        self::assertCount(1, $this->inner->getRecords());
    }

    public function testDelegatesIsHandlingToTheInnerHandler(): void
    {
        $handler = new ProbeFilterHandler(new TestHandler(Level::Error), '/healthz');

        self::assertFalse($handler->isHandling($this->record(Level::Info, 'https://scout.h.belovai.com/route')));
    }

    private function record(Level $level, string $requestUri): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'request',
            level: $level,
            message: 'Matched route "healthz".',
            context: ['request_uri' => $requestUri, 'method' => 'GET'],
        );
    }
}
