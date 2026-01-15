<?php

declare(strict_types=1);

namespace App\Billing\Domain;

use App\Billing\Model\Response\DocumentResponse;
use Doctrine\DBAL\Exception;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

readonly class BillSendUseCase
{
    public function __construct(
        private BillCreateUseCase $billCreateUseCase,
        private BillRepositoryInterface $billRepository,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function __invoke(string $saleId): DocumentResponse
    {
        $bill = $this->billCreateUseCase->__invoke($saleId);
        $response = $this->billRepository->sendBill($bill);
        $response->name = $bill->getName();

        return $response;
    }
}
