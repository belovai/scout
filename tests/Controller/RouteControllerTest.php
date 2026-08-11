<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Provider\Exception\NoRouteException;
use App\Provider\Exception\ProviderException;
use App\Provider\RouteProvider;
use App\Provider\RouteResult;
use App\Tests\Fake\FakeRouteProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RouteControllerTest extends WebTestCase
{
    private const AUTH = ['HTTP_AUTHORIZATION' => 'Bearer test-token', 'CONTENT_TYPE' => 'application/json'];

    private function clientWith(RouteResult|\Throwable $outcome): array
    {
        $client = static::createClient();
        $fake = new FakeRouteProvider($outcome);
        static::getContainer()->set(RouteProvider::class, $fake);

        return [$client, $fake];
    }

    private function post(KernelBrowser $client, string $body): void
    {
        $client->request('POST', '/route', server: self::AUTH, content: $body);
    }

    /** @return array<string, mixed> */
    private function json(KernelBrowser $client): array
    {
        return json_decode($client->getResponse()->getContent(), true);
    }

    public function testValidRequestReturnsDistanceDurationAndProvider(): void
    {
        [$client, $fake] = $this->clientWith(new RouteResult(12450, 1080));

        $this->post($client, '{"points":["example address","0.0000, 0.0000","example destination"]}');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            ['distance_meters' => 12450, 'duration_seconds' => 1080, 'provider' => 'fake'],
            $this->json($client),
        );
        self::assertSame(
            ['example address', '0.0000, 0.0000', 'example destination'],
            $fake->lastPoints,
        );
    }

    public function testMalformedJsonIsRejected(): void
    {
        [$client] = $this->clientWith(new RouteResult(1, 1));

        $this->post($client, 'not json');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'malformed json body'], $this->json($client));
    }

    public function testNonObjectJsonIsRejected(): void
    {
        [$client] = $this->clientWith(new RouteResult(1, 1));

        $this->post($client, '[1,2]');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'malformed json body'], $this->json($client));
    }

    public function testMissingPointsIsRejected(): void
    {
        [$client] = $this->clientWith(new RouteResult(1, 1));

        $this->post($client, '{}');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'points must be a list of strings'], $this->json($client));
    }

    public function testPointsAsObjectIsRejected(): void
    {
        [$client] = $this->clientWith(new RouteResult(1, 1));

        $this->post($client, '{"points":{"a":"b"}}');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'points must be a list of strings'], $this->json($client));
    }

    public function testNonStringElementIsRejected(): void
    {
        [$client] = $this->clientWith(new RouteResult(1, 1));

        $this->post($client, '{"points":["a",42]}');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'points must be a list of non-empty strings'], $this->json($client));
    }

    public function testBlankElementIsRejected(): void
    {
        [$client] = $this->clientWith(new RouteResult(1, 1));

        $this->post($client, '{"points":["a","   "]}');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'points must be a list of non-empty strings'], $this->json($client));
    }

    public function testSinglePointIsRejected(): void
    {
        [$client] = $this->clientWith(new RouteResult(1, 1));

        $this->post($client, '{"points":["a"]}');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'points must contain at least 2 elements'], $this->json($client));
    }

    public function testEmptyPointsIsRejected(): void
    {
        [$client] = $this->clientWith(new RouteResult(1, 1));

        $this->post($client, '{"points":[]}');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'points must contain at least 2 elements'], $this->json($client));
    }

    public function testNoRouteBecomes422WithoutProviderDetail(): void
    {
        [$client] = $this->clientWith(new NoRouteException('google says ZERO_RESULTS for Foo Street'));

        $this->post($client, '{"points":["a","b"]}');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'no route found'], $this->json($client));
        self::assertStringNotContainsString('ZERO_RESULTS', $client->getResponse()->getContent());
    }

    public function testProviderFailureBecomes502WithoutProviderDetail(): void
    {
        [$client] = $this->clientWith(new ProviderException('HTTP 429 quota exceeded for key AIzaSECRET'));

        $this->post($client, '{"points":["a","b"]}');

        self::assertResponseStatusCodeSame(502);
        self::assertSame(['error' => 'provider unavailable'], $this->json($client));
        self::assertStringNotContainsString('AIzaSECRET', $client->getResponse()->getContent());
    }

    public function testProviderFailureIsLogged(): void
    {
        [$client] = $this->clientWith(new ProviderException('HTTP 429 quota exceeded'));

        $this->post($client, '{"points":["a","b"]}');

        $records = static::getContainer()->get('monolog.handler.main')->getRecords();
        $messages = array_map(static fn ($r): string => $r['message'].' '.json_encode($r['context']), $records);

        self::assertNotEmpty(
            array_filter($messages, static fn (string $m): bool => str_contains($m, 'quota exceeded')),
            'expected the underlying provider error to be logged',
        );
    }

    public function testGetIsNotAllowed(): void
    {
        [$client] = $this->clientWith(new RouteResult(1, 1));

        $client->request('GET', '/route', server: self::AUTH);

        self::assertResponseStatusCodeSame(405);
    }
}
