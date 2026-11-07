<?php

declare(strict_types=1);

namespace App\AccountsPayable\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\AccountsPayable\Controller\SupplierBillForecastController;

#[ApiResource(shortName: 'SupplierBillForecast', operations: [
    new Get(
        uriTemplate: '/supplier-bills-forecast',
        controller: SupplierBillForecastController::class,
        security: "is_granted('perm:accounts_payable.view')",
        read: false,
    ),
])]
final class SupplierBillForecastResource {}
