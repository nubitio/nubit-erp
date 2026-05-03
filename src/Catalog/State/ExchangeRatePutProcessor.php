<?php

declare(strict_types=1);

namespace App\Catalog\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Entity\ExchangeRate;
use App\Catalog\Enum\ExchangeRateSource;
use App\Catalog\Repository\ExchangeRateRepository;
use App\Catalog\Service\ExchangeRateSyncService;
use Doctrine\ORM\EntityManagerInterface;

/** @implements ProcessorInterface<ExchangeRate, ExchangeRate> */
final readonly class ExchangeRatePutProcessor implements ProcessorInterface
{
    public function __construct(
        private ExchangeRateRepository $repository,
        private ExchangeRateSyncService $syncService,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ExchangeRate
    {
        /** @var ExchangeRate $data */

        // POST /exchange-rate/{currency}/sync → pull from BCRP
        if ($operation instanceof HttpOperation && str_ends_with((string) $operation->getUriTemplate(), '/sync')) {
            return $this->syncService->syncEntity($data->getCurrency());
        }

        // PUT /exchange-rate/{currency} → manual update
        $existing = $this->repository->findByCurrency($data->getCurrency());

        if ($existing !== null) {
            $existing->update($data->getRate(), ExchangeRateSource::MANUAL);
            $this->em->flush();
            return $existing;
        }

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}
