<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Controller\DashboardController;

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
