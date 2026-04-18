<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Domain;

use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use Brick\Math\Exception\MathException;
use Psr\Log\LoggerInterface;

readonly class BillSendUseCase
{
    public function __construct(
        private BillCreateUseCase $billCreateUseCase,
        private BillRepositoryInterface $billRepository,
        private LoggerInterface $billingLogger,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     * @throws MathException
     */
    public function __invoke(string $taxDocumentId): DocumentResponse
    {
        $this->billingLogger->info('BillSendUseCase invoked', ['taxDocumentId' => $taxDocumentId]);

        $bill = $this->billCreateUseCase->__invoke($taxDocumentId);
        $response = $this->billRepository->sendBill($bill);
        $response->name = $bill->getName();

        return $response;
    }
}
