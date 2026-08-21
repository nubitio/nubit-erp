<?php

declare(strict_types=1);

namespace App\Accounting\Controller;

use App\Accounting\Service\LedgerQuery;
use App\Accounting\Service\TrialBalanceQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AccountingReportController extends AbstractController
{
    public function __construct(
        private readonly TrialBalanceQuery $trialBalanceQuery,
        private readonly LedgerQuery $ledgerQuery,
    ) {}

    #[Route('/api/general-ledger/trial-balance', name: 'accounting_trial_balance', methods: ['GET'])]
    #[IsGranted('perm:accounting.view')]
    public function trialBalance(Request $request): JsonResponse
    {
        $period = (string) $request->query->get('period', date('Ym'));

        return $this->json($this->trialBalanceQuery->compute($period), Response::HTTP_OK);
    }

    #[Route('/api/general-ledger/ledger', name: 'accounting_ledger', methods: ['GET'])]
    #[IsGranted('perm:accounting.view')]
    public function ledger(Request $request): JsonResponse
    {
        $account = (string) $request->query->get('account', '101');
        $from = (string) $request->query->get('from', date('Y-m-01'));
        $to = (string) $request->query->get('to', date('Y-m-d'));

        return $this->json($this->ledgerQuery->compute($account, $from, $to), Response::HTTP_OK);
    }
}
