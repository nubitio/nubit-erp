<?php

declare(strict_types=1);

namespace App\Sales\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Sales\Controller\ReceivableSummaryController;

/**
 * KPI header for "Deudores" (#409) — see {@see ReceivableSummaryController}.
 */
#[ApiResource(shortName: 'ReceivableSummary', operations: [
    new Get(
        uriTemplate: '/sales/reports/receivable-summary',
        controller: ReceivableSummaryController::class,
        security: "is_granted('perm:sales.view')",
        read: false,
    ),
])]
final class ReceivableSummaryResource {}
