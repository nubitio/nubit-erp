<?php

declare(strict_types=1);

namespace App\Catalog\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Entity\ExchangeRate;
use App\Catalog\Enum\ExchangeRateSource;
use App\Catalog\Repository\ExchangeRateRepository;

/** @implements ProviderInterface<ExchangeRate> */
final readonly class ExchangeRateProvider implements ProviderInterface
{
    public function __construct(
        private ExchangeRateRepository $repository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ExchangeRate
    {
        $currency = strtoupper((string) ($uriVariables['currency'] ?? 'USD'));
        $rate = $this->repository->findByCurrency($currency);

        if ($rate === null) {
            $rate = new ExchangeRate($currency, '3.800000', ExchangeRateSource::MANUAL);
        }

        return $rate;
    }
}
