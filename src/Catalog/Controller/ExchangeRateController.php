<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Entity\ExchangeRate;
use App\Catalog\Enum\ExchangeRateSource;
use App\Catalog\Repository\ExchangeRateRepository;
use App\Catalog\Service\ExchangeRateSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @deprecated Use the API Platform endpoints GET/PUT /api/exchange-rate/{currency}
 *             and POST /api/exchange-rate/{currency}/sync instead.
 *             This controller is kept for backward compatibility during the
 *             transition period and will be removed in a future release.
 */
#[Route('/api/v1/exchange-rate')]
class ExchangeRateController extends AbstractController
{
    public function __construct(
        private readonly ExchangeRateRepository $repository,
        private readonly ExchangeRateSyncService $syncService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'exchange_rate_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $entity = $this->repository->findByCurrency('USD');

        return $this->json([
            'currency' => 'USD',
            'rate' => $entity?->getRate() ?? '3.800000',
            'updated_at' => $entity?->getUpdatedAt()->format('Y-m-d H:i:s'),
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
        $existing = $this->repository->findByCurrency('USD');

        if ($existing !== null) {
            $existing->update($formattedRate, ExchangeRateSource::MANUAL);
        } else {
            $existing = new ExchangeRate('USD', $formattedRate, ExchangeRateSource::MANUAL);
            $this->em->persist($existing);
        }
        $this->em->flush();

        return $this->json([
            'currency' => 'USD',
            'rate' => $existing->getRate(),
            'updated_at' => $existing->getUpdatedAt()->format('Y-m-d H:i:s'),
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
