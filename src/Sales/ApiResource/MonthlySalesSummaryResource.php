<?php

declare(strict_types=1);

namespace App\Sales\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Sales\Controller\MonthlySalesSummaryController;

#[ApiResource(shortName: 'MonthlySalesSummary', operations: [
    new Get(
        uriTemplate: '/sales/reports/monthly-summary',
        controller: MonthlySalesSummaryController::class,
        security: "is_granted('perm:sales.view')",
        read: false,
    ),
])]
final class MonthlySalesSummaryResource {}
