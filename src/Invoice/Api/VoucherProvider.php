<?php

namespace App\Invoice\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Exception;

readonly class VoucherProvider implements ProviderInterface
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
    ) {
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object | array | null
    {
        return $this->invoiceRepository->getVoucher(strval($uriVariables['sale_id']));
    }
}
