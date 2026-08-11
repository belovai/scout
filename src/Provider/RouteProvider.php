<?php

declare(strict_types=1);

namespace App\Provider;

use App\Provider\Exception\NoRouteException;
use App\Provider\Exception\ProviderException;

interface RouteProvider
{
    /**
     * @param string[] $points Ordered list, >=2 elements, each an address or "lat, lng" string,
     *                         passed through to the provider as-is.
     *
     * @throws NoRouteException  when the provider cannot find a route for the points
     * @throws ProviderException on transport/quota/provider failure
     */
    public function computeRoute(array $points): RouteResult;

    /**
     * Short provider identifier, e.g. "google".
     */
    public function name(): string;
}
