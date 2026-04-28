<?php

declare(strict_types=1);

namespace App\Controller;

use App\Efact\Service\SettingsService;
use App\Efact\Config\EfactConstants as Constants;
use App\Catalog\Service\ExchangeRateSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/exchange-rate')]
class ExchangeRateController extends AbstractController
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ExchangeRateSyncService $syncService,
    ) {
    }

    #[Route('', name: 'exchange_rate_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $rate = (string) ($this->settings->get(Constants::SALES_DEFAULT_USD_EXCHANGE_RATE, '3.800000') ?? '3.800000');
        $updatedAt = (string) ($this->settings->get(Constants::EXCHANGE_RATE_UPDATED_AT) ?? '');

        return $this->json([
            'currency' => 'USD',
            'rate' => $rate,
            'updated_at' => $updatedAt ?: null,
        ]);
    }

    #[Route('', name: 'exchange_rate_put', methods: ['PUT'])]
    public function put(Request $request): JsonResponse
    {
        /** @var array{rate?: mixed} $body */
        $body = json_decode((string) $request->getContent(), true) ?? [];
        $rawRate = $body['rate'] ?? null;

        if (!is_numeric($rawRate) || (float) $rawRate <= 0) {
            return $this->json(['error' => 'rate must be a positive number'], 400);
        }

        $formattedRate = number_format((float) $rawRate, 6, '.', '');
        $updatedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $this->settings->set(Constants::SALES_DEFAULT_USD_EXCHANGE_RATE, 'float', $formattedRate);
        $this->settings->set(Constants::EXCHANGE_RATE_UPDATED_AT, 'string', $updatedAt);

        return $this->json([
            'currency' => 'USD',
            'rate' => $formattedRate,
            'updated_at' => $updatedAt,
        ]);
    }

    #[Route('/sync', name: 'exchange_rate_sync', methods: ['POST'])]
    public function sync(): JsonResponse
    {
        $result = $this->syncService->sync();

        if (!$result['success']) {
            return $this->json(['error' => $result['message']], 502);
        }

        return $this->json([
            'currency' => 'USD',
            'rate' => $result['rate'],
            'updated_at' => $result['updated_at'],
        ]);
    }
}
