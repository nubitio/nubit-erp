<?php

use App\Domain\Compiler\RemoveUnwantedControllers;
use App\Domain\Compiler\RemoveUnwantedServices;
use App\Kernel;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

require_once("../vendor/autoload.php");

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/../.env');

$session = new Session();
$session->start();

$request = Request::createFromGlobals();
$request->setSession($session);

$kernel = new Kernel($_ENV['APP_ENV'], true);

try {
    $response = $kernel->handle($request);
    if ($response->getStatusCode() !== 404) {
        $response->send();
        $kernel->terminate($request, $response);
        exit();
    }
} catch (Exception $e) {
    dd($e);
    if (!$e instanceof NotFoundHttpException) {
        throw $e;
    }
}

// Retrieve instance of the framework
/** @var Base $app */
$app = Base::instance();

// Load configuration
$app->config(dirname(__FILE__, 2) . '/config/config.ini');

// Define routes
$app->config(dirname(__FILE__, 2) . '/config/routes.ini');

$app->set('CACHE', true);

$containerBuilder = new ContainerBuilder();
$loader = new PhpFileLoader($containerBuilder, new FileLocator(dirname(__FILE__, 2) . '/config'));

try {
    $loader->load('services.php');
} catch (Exception $e) {
    $app->error(500, $e->getMessage(), $e->getTrace());
}

$containerBuilder->addCompilerPass(new RemoveUnwantedServices());
$containerBuilder->addCompilerPass(new RemoveUnwantedControllers());
$containerBuilder->compile();

$app->set('request', $request);
$app->set('di', $containerBuilder);

$app->set('CONTAINER', function ($class) use ($containerBuilder) {
    return $containerBuilder->get($class);
});

if ($_ENV['APP_ENV'] != 'dev') {
    //\Sentry\init(['dsn' => 'https://709d3ebb8c184de9a5e579946b6e210b@o1246433.ingest.sentry.io/6405961']);
} else {
    Debug::enable();
}

$app->set('ONERROR', function ($app) {
    $exception = $app->get('EXCEPTION');
    if ($exception instanceof \Exception) {
        throw $exception;
    } else {
        dd($app->get('ERROR'));
    }
});

// Execute application
$app->run();
