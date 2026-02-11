<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Domain;

use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use Brick\Math\Exception\MathException;

readonly class BillSendUseCase
{
    public function __construct(
        private BillCreateUseCase $billCreateUseCase,
        private BillRepositoryInterface $billRepository,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     * @throws MathException
     */
    public function __invoke(string $taxDocumentId): DocumentResponse
    {
        $bill = $this->billCreateUseCase->__invoke($taxDocumentId);
        $response = $this->billRepository->sendBill($bill);
        $response->name = $bill->getName();

        return $response;
    }
}
