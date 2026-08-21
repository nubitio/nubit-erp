<?php

declare(strict_types=1);

namespace App\Onboarding\Controller;

use App\Onboarding\OpeningReceivableImporter;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/v1/onboarding/receivables')]
final class OpeningReceivableImportController extends AbstractController
{
    public function __construct(
        private readonly OpeningReceivableImporter $importer,
    ) {}

    #[Route('', name: 'onboarding_import_receivables', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $rows = is_array($payload) ? ($payload['rows'] ?? $payload) : null;
        if (!is_array($rows)) {
            return $this->json(['detail' => 'Envía { "rows": [ ... ] } con los saldos por cobrar.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            /** @var list<array<string, mixed>> $typedRows */
            $typedRows = array_values(array_filter($rows, 'is_array'));
            $result = $this->importer->import($typedRows);
        } catch (RuntimeException $exception) {
            return $this->json(['detail' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($result, Response::HTTP_OK);
    }
}
