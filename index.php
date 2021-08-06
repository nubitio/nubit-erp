<?php

require_once("vendor/autoload.php");

// Retrieve instance of the framework
/** @var Base $app */
$app = Base::instance();

// Initialize src
$app->config('src/config.ini');

// Define routes
$app->config('src/routes.ini');

$app->set('CACHE', true);

// Define version
$app->set('version', '1.0.6');

// Execute application
$app->run();
