<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Infrastructure;

use App\Billing\Sunat\Domain\CreditTermsProviderInterface;
use App\Efact\Domain\Money;
use App\Sales\Repository\SaleRecordRepository;
use DateTimeImmutable;
use Override;

final readonly class SaleCreditTermsProvider implements CreditTermsProviderInterface
{
    public function __construct(
        private SaleRecordRepository $sales,
    ) {}

    #[Override]
    public function termsFor(?string $saleId): ?array
    {
        if ($saleId === null || $saleId === '') {
            return null;
        }

        $sale = $this->sales->find($saleId);
        $dueDate = $sale?->getDueDate();
        if ($sale === null || $dueDate === null) {
            return null;
        }

        $pending = Money::money($sale->getBalanceDue());
        if (!$pending->isPositive()) {
            return null;
        }

        return ['amount' => Money::toFloat($pending), 'dueDate' => DateTimeImmutable::createFromInterface($dueDate)];
    }
}
