<?php

declare(strict_types=1);

namespace App\Provider\Google;

use App\Provider\Exception\NoRouteException;
use App\Provider\Exception\ProviderException;
use App\Provider\RouteProvider;
use App\Provider\RouteResult;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleRoutesProvider implements RouteProvider
{
    private const ENDPOINT = 'https://routes.googleapis.com/directions/v2:computeRoutes';
    private const FIELD_MASK = 'routes.distanceMeters,routes.duration';
    private const TIMEOUT_SECONDS = 10;
    private const COORDINATE_PATTERN = '/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/';
    private const DURATION_PATTERN = '/^(\d+(?:\.\d+)?)s$/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
    ) {
    }

    public function computeRoute(array $points): RouteResult
    {
        $payload = $this->decode($this->send($this->buildBody($points)));

        return $this->toResult($payload);
    }

    public function name(): string
    {
        return 'google';
    }

    /**
     * @param string[] $points
     *
     * @return array<string, mixed>
     */
    private function buildBody(array $points): array
    {
        $points = array_values($points);
        $waypoints = array_map($this->toWaypoint(...), $points);

        $body = [
            'origin' => array_shift($waypoints),
            'destination' => array_pop($waypoints),
            'travelMode' => 'DRIVE',
            'routingPreference' => 'TRAFFIC_AWARE',
        ];

        // departureTime is intentionally omitted: Google defaults to "now" for traffic-aware
        // requests, and sending an explicit now can be rejected as being in the past on clock skew.

        if ([] !== $waypoints) {
            $body['intermediates'] = array_values($waypoints);
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function toWaypoint(string $point): array
    {
        if (1 === preg_match(self::COORDINATE_PATTERN, $point, $matches)) {
            return ['location' => ['latLng' => [
                'latitude' => (float) $matches[1],
                'longitude' => (float) $matches[2],
            ]]];
        }

        return ['address' => $point];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function send(array $body): array
    {
        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => self::FIELD_MASK,
                ],
                'body' => json_encode($body, JSON_THROW_ON_ERROR),
                'timeout' => self::TIMEOUT_SECONDS,
                'max_duration' => self::TIMEOUT_SECONDS,
            ]);

            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (HttpExceptionInterface|\JsonException $e) {
            throw new ProviderException('google routes request failed: '.$e->getMessage(), 0, $e);
        }

        if ($status < 200 || $status >= 300) {
            throw new ProviderException(sprintf('google routes returned HTTP %d: %s', $status, $content));
        }

        return ['content' => $content];
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    private function decode(array $raw): array
    {
        try {
            $decoded = json_decode((string) $raw['content'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ProviderException('google routes returned unparsable JSON: '.$e->getMessage(), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new ProviderException('google routes returned a non-object JSON body');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function toResult(array $payload): RouteResult
    {
        $route = $payload['routes'][0] ?? null;

        if (!is_array($route) || !isset($route['distanceMeters'], $route['duration'])) {
            throw new NoRouteException('google routes returned no usable route');
        }

        if (1 !== preg_match(self::DURATION_PATTERN, (string) $route['duration'], $matches)) {
            throw new NoRouteException('google routes returned an unparsable duration');
        }

        return new RouteResult((int) $route['distanceMeters'], (int) $matches[1]);
    }
}
