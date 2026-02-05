<?php

declare(strict_types=1);

namespace App\Invoice\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\TaxDocumentRepository;
use Doctrine\DBAL\Exception;
use Override;

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
        return $this->taxDocumentRepository->getVoucher(strval($uriVariables['sale_id']));
    }
}
