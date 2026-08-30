<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Domain;

use App\Billing\Invoice\Domain\Voucher;
use App\Billing\Sunat\Model\Response\DocumentResponse;
use Brick\Math\Exception\MathException;
use Psr\Log\LoggerInterface;

readonly class BillSendUseCase
{
    public function __construct(
        private BillCreateUseCase $billCreateUseCase,
        private BillRepositoryInterface $billRepository,
        private LoggerInterface $billingLogger,
    ) {}

    /**
     * @throws \DateMalformedStringException
     * @throws MathException
     */
    public function __invoke(string $taxDocumentId, ?Voucher $voucher = null): DocumentResponse
    {
        $this->billingLogger->info('BillSendUseCase invoked', ['taxDocumentId' => $taxDocumentId]);

        $bill = $this->billCreateUseCase->__invoke($taxDocumentId, $voucher);
        $response = $this->billRepository->sendBill($bill);
        $response->name = $bill->getName();

        return $response;
    }
}
