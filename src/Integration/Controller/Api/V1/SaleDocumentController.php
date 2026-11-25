<?php

declare(strict_types=1);

namespace App\Integration\Controller\Api\V1;

use App\Integration\Sales\SaleFiscalRequests;
use App\Integration\Security\ApiClientUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SaleDocumentController extends AbstractController
{
    #[Route('/api/v1/sales/{id}/document', name: 'integration_v1_sale_document_request', methods: ['POST'])]
    #[IsGranted('ROLE_SCOPE_DOCUMENTS_WRITE')]
    public function request(string $id, SaleFiscalRequests $requests): JsonResponse
    {
        return $this->respond($id, $requests, true);
    }

    #[Route('/api/v1/sales/{id}/document', name: 'integration_v1_sale_document_status', methods: ['GET'])]
    #[IsGranted('ROLE_SCOPE_DOCUMENTS_READ')]
    public function status(string $id, SaleFiscalRequests $requests): JsonResponse
    {
        return $this->respond($id, $requests, false);
    }

    private function respond(string $id, SaleFiscalRequests $requests, bool $create): JsonResponse
    {
        $client = $this->getUser();
        if (!$client instanceof ApiClientUser) {
            throw new AccessDeniedException();
        }

        try {
            $data = $create ? $requests->request($client, $id) : $requests->find($client, $id);
            if ($data === null) {
                throw new NotFoundHttpException('Document request not found.');
            }
        } catch (NotFoundHttpException|UnprocessableEntityHttpException $e) {
            return new JsonResponse(['error' => [
                'code' => $e->getStatusCode() === 404 ? 'not_found' : 'validation_failed',
                'message' => $e->getMessage(),
            ]], $e->getStatusCode());
        }

        return new JsonResponse($data, $create && $data['status'] === 'queued' ? 202 : 200);
    }
}
