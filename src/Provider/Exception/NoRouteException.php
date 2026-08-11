<?php

declare(strict_types=1);

namespace App\Provider\Exception;

/**
 * The provider answered successfully but could not find a route for the points.
 */
final class NoRouteException extends \RuntimeException
{
}
