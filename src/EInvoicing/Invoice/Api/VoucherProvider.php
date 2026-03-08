<?php

declare(strict_types=1);

namespace App\EInvoicing\Invoice\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\TaxDocumentRepository;
use Doctrine\DBAL\Exception;
use Override;

/**
 * @implements ProviderInterface<object>
 */
readonly class VoucherProvider implements ProviderInterface
{
    public function __construct(
        private TaxDocumentRepository $taxDocumentRepository,
    ) {
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object | array | null
    {
        if (isset($uriVariables['tax_document_id'])) {
            return $this->taxDocumentRepository->getVoucherByTaxDocumentId(strval($uriVariables['tax_document_id']));
        }

        return $this->taxDocumentRepository->getVoucher(strval($uriVariables['sale_id']));
    }
}
