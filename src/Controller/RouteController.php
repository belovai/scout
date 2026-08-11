<?php

declare(strict_types=1);

namespace App\Controller;

use App\Provider\Exception\NoRouteException;
use App\Provider\Exception\ProviderException;
use App\Provider\RouteProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RouteController
{
    public function __construct(
        private readonly RouteProvider $provider,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/route', name: 'route', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $points = $this->extractPoints($request->getContent());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->provider->computeRoute($points);
        } catch (NoRouteException) {
            return $this->error('no route found', Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ProviderException $e) {
            $this->logger->error('route provider failed', [
                'provider' => $this->provider->name(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return $this->error('provider unavailable', Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'distance_meters' => $result->distanceMeters,
            'duration_seconds' => $result->durationSeconds,
            'provider' => $this->provider->name(),
        ]);
    }

    /**
     * @return string[]
     *
     * @throws \InvalidArgumentException with a client-safe message
     */
    private function extractPoints(string $body): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('malformed json body');
        }

        if (!is_array($decoded) || ([] !== $decoded && array_is_list($decoded))) {
            throw new \InvalidArgumentException('malformed json body');
        }

        $points = $decoded['points'] ?? null;

        if (!is_array($points) || !array_is_list($points)) {
            throw new \InvalidArgumentException('points must be a list of strings');
        }

        foreach ($points as $point) {
            if (!is_string($point) || '' === trim($point)) {
                throw new \InvalidArgumentException('points must be a list of non-empty strings');
            }
        }

        if (count($points) < 2) {
            throw new \InvalidArgumentException('points must contain at least 2 elements');
        }

        return $points;
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
