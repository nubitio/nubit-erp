<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

require_once("../vendor/autoload.php");

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/../.env');

$session = new Session();
$session->start();

$request = Request::createFromGlobals();
$request->setSession($session);

$kernel = new Kernel($_ENV['APP_ENV'], true);

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
