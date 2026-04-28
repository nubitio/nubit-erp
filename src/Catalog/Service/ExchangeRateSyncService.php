<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Efact\Service\SettingsService;
use App\Efact\Config\EfactConstants as Constants;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

readonly class ExchangeRateSyncService
{
    // BCRP serie PD04638PD = tipo de cambio venta USD/PEN
    private const string BCRP_URL =
        'https://estadisticas.bcrp.gob.pe/estadisticas/series/api/PD04638PD/json/hoy/hoy/ing';

    public function __construct(
        private SettingsService $settings,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{success: bool, rate: ?string, updated_at: ?string, message: string}
     */
    public function sync(): array
    {
        try {
            $response = $this->httpClient->request('GET', self::BCRP_URL, ['timeout' => 10]);
            $data = $response->toArray();
            $rate = $this->parseRate($data);

            if ($rate === null) {
                $this->logger->warning(
                    'BCRP response did not contain a valid exchange rate',
                    ['data' => $data]
                );

                return [
                    'success' => false,
                    'rate' => null,
                    'updated_at' => null,
                    'message' => 'BCRP did not return a valid rate',
                ];
            }

            $formattedRate = number_format($rate, 6, '.', '');
            $updatedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

            $this->settings->set(Constants::SALES_DEFAULT_USD_EXCHANGE_RATE, 'float', $formattedRate);
            $this->settings->set(Constants::EXCHANGE_RATE_UPDATED_AT, 'string', $updatedAt);

            return [
                'success' => true,
                'rate' => $formattedRate,
                'updated_at' => $updatedAt,
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
