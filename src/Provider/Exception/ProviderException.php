<?php

declare(strict_types=1);

namespace App\Provider\Exception;

/**
 * The provider call failed: transport error, quota, non-2xx status, unparsable body.
 * The message is for logs only and must never reach an HTTP response body.
 */
final class ProviderException extends \RuntimeException
{
}
