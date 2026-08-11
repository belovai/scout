<?php

use App\Config\EnvGuard;
use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    EnvGuard::assert($context);

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
