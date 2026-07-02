<?php

declare(strict_types=1);

namespace App\Taxes\Api;

use App\Taxes\Application\Query\DetraccionQuery;
use App\Taxes\Application\Query\RetencionPercepcionQuery;
use App\Taxes\Application\Query\TaxSummaryQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/taxes')]
#[IsGranted('ROLE_USER')]
class TaxController extends AbstractController
{
    #[Route('/igv-summary', methods: ['GET'])]
    public function igvSummary(Request $request, TaxSummaryQuery $query): JsonResponse
    {
        $period = (string) $request->query->get('period', '');

        return $this->json($query->compute($period));
    }

    #[Route('/detracciones', methods: ['GET'])]
    public function detracciones(Request $request, DetraccionQuery $query): JsonResponse
    {
        $period = (string) $request->query->get('period', '');

        return $this->json($query->forPeriod($period));
    }

    #[Route('/retenciones-percepciones', methods: ['GET'])]
    public function retencionesPercepciones(Request $request, RetencionPercepcionQuery $query): JsonResponse
    {
        $period = (string) $request->query->get('period', '');

        return $this->json($query->forPeriod($period));
    }
}
