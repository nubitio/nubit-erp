<?php

declare(strict_types=1);

namespace App\AccountsPayable\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\AccountsPayable\Controller\SupplierBillSummaryController;

#[ApiResource(shortName: 'SupplierBillSummary', operations: [
    new Get(
        uriTemplate: '/supplier-bills-summary',
        controller: SupplierBillSummaryController::class,
        security: "is_granted('perm:accounts_payable.view')",
        read: false,
    ),
])]
final class SupplierBillSummaryResource {}
