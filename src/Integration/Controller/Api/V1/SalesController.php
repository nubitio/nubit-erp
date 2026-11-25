<?php

declare(strict_types=1);

namespace App\Integration\Controller\Api\V1;

use App\Integration\Sales\CreditSaleInput;
use App\Integration\Sales\SalesClientAccess;
use App\Integration\Sales\SalesIntake;
use App\Integration\Security\ApiClientUser;
use App\Sales\Port\ConfirmIntegrationCredit;
use Nubit\Platform\Exception\ServiceException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SalesController extends AbstractController
{
    #[Route('/api/v1/sales', name: 'integration_v1_sales_create', methods: ['POST'])]
    #[IsGranted('ROLE_SCOPE_SALES_WRITE')]
    public function create(Request $request, SalesIntake $intake, ConfirmIntegrationCredit $confirm): JsonResponse
    {
        $client = $this->getUser();
        if (!$client instanceof ApiClientUser) {
            return self::error('forbidden', 'Integration credentials are required.', 403);
        }
        $content = $request->getContent();
        if (strlen($content) > 65_536) {
            return self::error('payload_too_large', 'The sales request exceeds 64 KiB.', 413);
        }
        $body = json_decode($content, associative: true);
        if (!is_array($body) || array_is_list($body)) {
            return self::error('validation_failed', 'The request body must be a JSON object.', 400);
        }

        try {
            $input = CreditSaleInput::fromArray($body);
            $result = $intake->submit(
                $client,
                $input->branchId,
                $request->headers->get('Idempotency-Key') ?? '',
                $input->fingerprint(),
                static fn(string $clientId, string $branchId): string => $confirm->confirmIntegrationCredit(
                    $clientId,
                    $branchId,
                    $input->warehouseId,
                    $input->customerId,
                    $input->items,
                    $input->expectedTotal,
                ),
            );
        } catch (ConflictHttpException $e) {
            return self::error('idempotency_conflict', $e->getMessage(), 409);
        } catch (AccessDeniedException) {
            return self::error('forbidden', 'The client lacks permission for this sales command.', 403);
        } catch (ServiceException|UnprocessableEntityHttpException $e) {
            return self::error('validation_failed', $e->getMessage(), 422);
        }

        return new JsonResponse($result, $result['replayed'] ? 200 : 201);
    }

    #[Route('/api/v1/sales/{id}', name: 'integration_v1_sales_get', methods: ['GET'])]
    #[IsGranted('ROLE_SCOPE_SALES_READ')]
    public function get(string $id, SalesClientAccess $access): JsonResponse
    {
        $client = $this->getUser();
        if (!$client instanceof ApiClientUser) {
            return self::error('forbidden', 'Integration credentials are required.', 403);
        }

        $sale = $access->findSale($client, $id);

        return $sale === null ? self::error('not_found', 'Sale not found.', 404) : new JsonResponse($sale);
    }

    private static function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
