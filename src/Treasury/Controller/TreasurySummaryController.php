<?php

declare(strict_types=1);

namespace App\Treasury\Controller;

use App\Settings\Capability\TenantCapabilityResolver;
use App\Treasury\Report\FinanceSummaryQuery;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** The owner's finance summary: money, what is owed to and by the business, and what needs attention. */
final class TreasurySummaryController extends TreasuryApiController
{
    public function __construct(
        TenantCapabilityResolver $capabilities,
        private readonly FinanceSummaryQuery $summary,
    ) {
        parent::__construct($capabilities);
    }

    #[Route('/api/treasury/summary', name: 'treasury_summary', methods: ['GET'])]
    #[IsGranted('perm:treasury.view')]
    public function summary(): JsonResponse
    {
        $this->requireCapability();

        return $this->json($this->summary->summarize(new DateTimeImmutable('today', new DateTimeZone('America/Lima'))));
    }
}
