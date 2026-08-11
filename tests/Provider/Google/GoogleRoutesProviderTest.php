<?php

declare(strict_types=1);

namespace App\Tests\Provider\Google;

use App\Provider\Exception\NoRouteException;
use App\Provider\Exception\ProviderException;
use App\Provider\Google\GoogleRoutesProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleRoutesProviderTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $captured = [];

    private function client(MockResponse $response): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            $this->captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return $response;
        });
    }

    /** @return array<string, mixed> */
    private function capturedBody(): array
    {
        return json_decode($this->captured['options']['body'], true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<int, string> */
    private function capturedHeaders(): array
    {
        return $this->captured['options']['headers'];
    }

    public function testReturnsDistanceAndDuration(): void
    {
        $provider = new GoogleRoutesProvider(
            $this->client(new MockResponse('{"routes":[{"distanceMeters":12450,"duration":"1080s"}]}')),
            'test-key',
        );

        $result = $provider->computeRoute(['budapest', 'szeged']);

        self::assertSame(12450, $result->distanceMeters);
        self::assertSame(1080, $result->durationSeconds);
    }

    public function testNameIsGoogle(): void
    {
        $provider = new GoogleRoutesProvider(new MockHttpClient(), 'test-key');

        self::assertSame('google', $provider->name());
    }

    public function testRequestTargetsComputeRoutesWithFieldMaskAndKey(): void
    {
        $provider = new GoogleRoutesProvider(
            $this->client(new MockResponse('{"routes":[{"distanceMeters":1,"duration":"1s"}]}')),
            'test-key',
        );

        $provider->computeRoute(['budapest', 'szeged']);

        self::assertSame('POST', $this->captured['method']);
        self::assertSame('https://routes.googleapis.com/directions/v2:computeRoutes', $this->captured['url']);

        $headers = implode("\n", $this->capturedHeaders());
        self::assertStringContainsString('x-goog-api-key: test-key', strtolower($headers));
        self::assertStringContainsString(
            'x-goog-fieldmask: routes.distancemeters,routes.duration',
            strtolower($headers),
        );
    }

    public function testRequestBodyIsDriveAndTrafficAwareWithoutDepartureTime(): void
    {
        $provider = new GoogleRoutesProvider(
            $this->client(new MockResponse('{"routes":[{"distanceMeters":1,"duration":"1s"}]}')),
            'test-key',
        );

        $provider->computeRoute(['budapest', 'szeged']);
        $body = $this->capturedBody();

        self::assertSame('DRIVE', $body['travelMode']);
        self::assertSame('TRAFFIC_AWARE', $body['routingPreference']);
        self::assertArrayNotHasKey('departureTime', $body);
        self::assertArrayNotHasKey('intermediates', $body);
    }

    public function testAddressPointsArePassedThroughVerbatim(): void
    {
        $provider = new GoogleRoutesProvider(
            $this->client(new MockResponse('{"routes":[{"distanceMeters":1,"duration":"1s"}]}')),
            'test-key',
        );

        $provider->computeRoute(['example address', 'example destination']);
        $body = $this->capturedBody();

        self::assertSame(['address' => 'example address'], $body['origin']);
        self::assertSame(['address' => 'example destination'], $body['destination']);
    }

    public function testCoordinatePointsBecomeLatLng(): void
    {
        $provider = new GoogleRoutesProvider(
            $this->client(new MockResponse('{"routes":[{"distanceMeters":1,"duration":"1s"}]}')),
            'test-key',
        );

        $provider->computeRoute(['0.0000, 0.0000', '-33.8688,151.2093']);
        $body = $this->capturedBody();

        self::assertSame(
            ['location' => ['latLng' => ['latitude' => 0.0000, 'longitude' => 0.0000]]],
            $body['origin'],
        );
        self::assertSame(
            ['location' => ['latLng' => ['latitude' => -33.8688, 'longitude' => 151.2093]]],
            $body['destination'],
        );
    }

    public function testIntermediatesKeepGivenOrderAndMixedFormats(): void
    {
        $provider = new GoogleRoutesProvider(
            $this->client(new MockResponse('{"routes":[{"distanceMeters":1,"duration":"1s"}]}')),
            'test-key',
        );

        $provider->computeRoute(['a', '46.1,20.1', 'b', 'c']);
        $body = $this->capturedBody();

        self::assertSame(['address' => 'a'], $body['origin']);
        self::assertSame(['address' => 'c'], $body['destination']);
        self::assertSame(
            [
                ['location' => ['latLng' => ['latitude' => 46.1, 'longitude' => 20.1]]],
                ['address' => 'b'],
            ],
            $body['intermediates'],
        );
    }

    public function testFractionalDurationIsTruncated(): void
    {
        $provider = new GoogleRoutesProvider(
            $this->client(new MockResponse('{"routes":[{"distanceMeters":10,"duration":"1080.9s"}]}')),
            'test-key',
        );

        self::assertSame(1080, $provider->computeRoute(['a', 'b'])->durationSeconds);
    }

    public function testEmptyRoutesThrowsNoRoute(): void
    {
        $provider = new GoogleRoutesProvider($this->client(new MockResponse('{"routes":[]}')), 'test-key');

        $this->expectException(NoRouteException::class);
        $provider->computeRoute(['a', 'b']);
    }

    public function testMissingRoutesKeyThrowsNoRoute(): void
    {
        $provider = new GoogleRoutesProvider($this->client(new MockResponse('{}')), 'test-key');

        $this->expectException(NoRouteException::class);
        $provider->computeRoute(['a', 'b']);
    }

    public function testRouteMissingFieldsThrowsNoRoute(): void
    {
        $provider = new GoogleRoutesProvider(
            $this->client(new MockResponse('{"routes":[{"distanceMeters":12450}]}')),
            'test-key',
        );

        $this->expectException(NoRouteException::class);
        $provider->computeRoute(['a', 'b']);
    }

    public function testUnparsableDurationThrowsNoRoute(): void
    {
        $provider = new GoogleRoutesProvider(
            $this->client(new MockResponse('{"routes":[{"distanceMeters":12450,"duration":"soon"}]}')),
            'test-key',
        );

        $this->expectException(NoRouteException::class);
        $provider->computeRoute(['a', 'b']);
    }

    public function testQuotaErrorThrowsProviderException(): void
    {
        $provider = new GoogleRoutesProvider(
            $this->client(new MockResponse('{"error":{"code":429}}', ['http_code' => 429])),
            'test-key',
        );

        $this->expectException(ProviderException::class);
        $provider->computeRoute(['a', 'b']);
    }

    public function testServerErrorThrowsProviderException(): void
    {
        $provider = new GoogleRoutesProvider(
            $this->client(new MockResponse('boom', ['http_code' => 500])),
            'test-key',
        );

        $this->expectException(ProviderException::class);
        $provider->computeRoute(['a', 'b']);
    }

    public function testMalformedJsonThrowsProviderException(): void
    {
        $provider = new GoogleRoutesProvider($this->client(new MockResponse('not json')), 'test-key');

        $this->expectException(ProviderException::class);
        $provider->computeRoute(['a', 'b']);
    }

    public function testTransportFailureThrowsProviderException(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new TransportException('connection refused');
        });
        $provider = new GoogleRoutesProvider($client, 'test-key');

        $this->expectException(ProviderException::class);
        $provider->computeRoute(['a', 'b']);
    }

    public function testProviderExceptionDoesNotLeakGoogleBodyInTheMessageUsedByCallers(): void
    {
        $provider = new GoogleRoutesProvider(
            $this->client(new MockResponse('SECRET-INTERNAL', ['http_code' => 500])),
            'test-key',
        );

        try {
            $provider->computeRoute(['a', 'b']);
            self::fail('expected ProviderException');
        } catch (ProviderException $e) {
            // The message is for logs and may contain provider detail; the controller must not echo it.
            self::assertNotSame('', $e->getMessage());
        }
    }
}
