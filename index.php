<?php

/**
 * @Author: Johan Guerreros <johangm90@gmail.com>
 * @License: Copyright (c) 2016. All Rights Reserved.
 */

require_once("vendor/autoload.php");

// Retrieve instance of the framework
/** @var Base $app */
$app = Base::instance();

// Initialize app
$app->config('app/config.ini');

// Define routes
$app->config('app/routes.ini');

// Define version
$app->set('version', '1.0.3');

// Execute application
$app->run();
