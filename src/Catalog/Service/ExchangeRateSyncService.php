<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Entity\ExchangeRate;
use App\Catalog\Enum\ExchangeRateSource;
use App\Catalog\Repository\ExchangeRateRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

readonly class ExchangeRateSyncService
{
    // BCRP serie PD04638PD = tipo de cambio venta USD/PEN
    private const string BCRP_URL = 'https://estadisticas.bcrp.gob.pe/estadisticas/series/api/PD04638PD/json/hoy/hoy/ing';

    public function __construct(
        private ExchangeRateRepository $repository,
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {}

    /**
     * Syncs the USD rate from BCRP and returns the updated entity.
     *
     * @return array{success: bool, rate: ?string, updated_at: ?string, message: string}
     */
    public function sync(): array
    {
        try {
            $entity = $this->syncEntity('USD');

            return [
                'success' => true,
                'rate' => $entity->getRate(),
                'updated_at' => $entity->getUpdatedAt()->format('Y-m-d H:i:s'),
                'message' => 'Rate synced successfully',
            ];
        } catch (Throwable $e) {
            $this->logger->warning('Failed to sync exchange rate from BCRP', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'rate' => null,
                'updated_at' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Syncs a specific currency from BCRP and persists it.
     * Throws on network/parse failure.
     */
    public function syncEntity(string $currency): ExchangeRate
    {
        $response = $this->httpClient->request('GET', self::BCRP_URL, ['timeout' => 10]);
        $data = $response->toArray();
        $rate = $this->parseRate($data);

        if ($rate === null) {
            throw new \RuntimeException('BCRP did not return a valid rate');
        }

        $formattedRate = number_format($rate, 6, '.', '');
        $existing = $this->repository->findByCurrency($currency);

        if ($existing !== null) {
            $existing->update($formattedRate, ExchangeRateSource::BCRP_AUTO);
            $this->em->flush();
            return $existing;
        }

        $entity = new ExchangeRate($currency, $formattedRate, ExchangeRateSource::BCRP_AUTO);
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    /**
     * @param array<mixed> $data
     */
    private function parseRate(array $data): ?float
    {
        $periods = $data['periods'] ?? null;
        if (!is_array($periods) || $periods === []) {
            return null;
        }

        foreach (array_reverse($periods) as $period) {
            $values = $period['values'] ?? null;
            if (!is_array($values)) {
                continue;
            }

            $raw = $values[0] ?? null;
            if ($raw === null || $raw === 'n.d.' || $raw === '') {
                continue;
            }

            $rate = filter_var($raw, FILTER_VALIDATE_FLOAT);
            if ($rate !== false && $rate > 0) {
                return $rate;
            }
        }

        return null;
    }
}
