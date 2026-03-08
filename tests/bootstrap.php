<?php

use Symfony\Component\Dotenv\Dotenv;

// Disable the OpenTelemetry SDK before Composer auto-instrumentation runs.
// OTEL reads env vars at autoload time (via autoload.files). Setting this here
// ensures no shutdown handler is registered, preventing connection errors when
// otel-collector is not available (e.g., unit tests, CI).
putenv('OTEL_SDK_DISABLED=true');
$_ENV['OTEL_SDK_DISABLED'] = 'true';
$_SERVER['OTEL_SDK_DISABLED'] = 'true';

require dirname(__DIR__) . '/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
