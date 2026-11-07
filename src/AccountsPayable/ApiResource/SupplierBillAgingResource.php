<?php

declare(strict_types=1);

namespace App\AccountsPayable\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\AccountsPayable\Controller\PayablesAgingController;

#[ApiResource(shortName: 'SupplierBillAging', operations: [
    new Get(
        uriTemplate: '/supplier-bills-aging',
        controller: PayablesAgingController::class . '::summary',
        security: "is_granted('perm:accounts_payable.view')",
        read: false,
    ),
    new Get(
        uriTemplate: '/supplier-bills-aging/export',
        controller: PayablesAgingController::class . '::export',
        security: "is_granted('perm:accounts_payable.view')",
        read: false,
        name: 'supplier_bills_aging_export',
    ),
])]
final class SupplierBillAgingResource {}
