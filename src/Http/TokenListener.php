<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 40)]
final class TokenListener
{
    private const PUBLIC_PATHS = ['/healthz'];

    public function __construct(private readonly string $apiToken)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (in_array($event->getRequest()->getPathInfo(), self::PUBLIC_PATHS, true)) {
            return;
        }

        if (!$this->hasValidToken((string) $event->getRequest()->headers->get('Authorization', ''))) {
            $event->setResponse(new JsonResponse(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED));
        }
    }

    private function hasValidToken(string $header): bool
    {
        if (!str_starts_with($header, 'Bearer ')) {
            return false;
        }

        $token = trim(substr($header, 7));

        return '' !== $token && hash_equals($this->apiToken, $token);
    }
}
