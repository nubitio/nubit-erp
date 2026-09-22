<?php

use Symfony\Component\Dotenv\Dotenv;

// Disable the OpenTelemetry SDK before Composer auto-instrumentation runs.
// OTEL reads env vars at autoload time (via autoload.files). Setting this here
// ensures no shutdown handler is registered, preventing connection errors when
// otel-collector is not available (e.g., unit tests, CI).
putenv('OTEL_SDK_DISABLED=true');
$_ENV['OTEL_SDK_DISABLED'] = 'true';
$_SERVER['OTEL_SDK_DISABLED'] = 'true';

// Keep direct `vendor/bin/pest` invocations safe and consistent with the
// PHPUnit configuration and Makefile. Without this, Dotenv can load the
// development environment and kernel tests fail before the first assertion.
putenv('APP_ENV=test');
$_ENV['APP_ENV'] = 'test';
$_SERVER['APP_ENV'] = 'test';

require dirname(__DIR__) . '/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
