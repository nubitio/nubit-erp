<?php

declare(strict_types=1);

namespace App\Sales\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Sales\Controller\DashboardController;

#[ApiResource(
    uriTemplate: '/dashboard',
    shortName: 'Dashboard',
    operations: [
        new Get(
            uriTemplate: '/bi/{type}',
            controller: DashboardController::class,
            read: false,
        ),
    ]
)]
class DashboardResource
{
}
