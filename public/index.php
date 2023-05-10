<?php

use App\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

$maintenanceFile = dirname(__DIR__) . '/.maintenance';

if (file_exists($maintenanceFile)) {
    $maintenancePage = dirname(__DIR__) . '/templates/maintenance/index.html';
    http_response_code(503);
    echo file_get_contents($maintenancePage);
    exit;
}

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool)$context['APP_DEBUG']);
};
