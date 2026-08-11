<?php

declare(strict_types=1);

namespace App\Tests\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TokenListenerTest extends WebTestCase
{
    public function testHealthzNeedsNoToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/healthz');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('OK', $client->getResponse()->getContent());
    }

    public function testMissingHeaderIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/route');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'unauthorized'], json_decode($client->getResponse()->getContent(), true));
    }

    public function testWrongSchemeIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/route', server: ['HTTP_AUTHORIZATION' => 'Basic test-token']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testWrongTokenIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/route', server: ['HTTP_AUTHORIZATION' => 'Bearer nope']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testEmptyBearerTokenIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/route', server: ['HTTP_AUTHORIZATION' => 'Bearer ']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testValidTokenPassesTheListener(): void
    {
        $client = static::createClient();
        $client->request('POST', '/route', server: ['HTTP_AUTHORIZATION' => 'Bearer test-token']);

        // The route does not exist yet; what matters is that this is not a 401.
        self::assertNotSame(401, $client->getResponse()->getStatusCode());
    }
}
