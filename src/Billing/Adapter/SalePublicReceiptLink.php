<?php

declare(strict_types=1);

namespace App\Billing\Adapter;

use App\Billing\Receipt\ReceiptPayload;
use App\Sales\Entity\SaleRecord;
use App\Sales\Port\SaleDocumentLinkInterface;
use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SalePublicReceiptLink implements SaleDocumentLinkInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public function publicUrl(SaleRecord $sale): ?string
    {
        $taxDocumentId = $sale->getTaxDocumentId();
        $documentType = $sale->getDocumentType();
        if ($taxDocumentId === null || $documentType === null) {
            return null;
        }

        return $this->urlGenerator->generate(
            'receipt_show',
            ['payload' => ReceiptPayload::encode($documentType, $taxDocumentId)],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
