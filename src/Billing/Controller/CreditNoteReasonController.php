<?php

declare(strict_types=1);

namespace App\Billing\Controller;

use App\Billing\Shared\Enum\CreditNoteReason;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Expone el catálogo SUNAT 09 (motivos de nota de crédito) al frontend para
 * poblar el selector al crear una nota de crédito.
 */
#[Route('/api/v1/credit-notes')]
final class CreditNoteReasonController extends AbstractController
{
    #[Route('/reasons', name: 'credit_note_reasons', methods: ['GET'])]
    public function reasons(): JsonResponse
    {
        return $this->json(CreditNoteReason::options());
    }
}
