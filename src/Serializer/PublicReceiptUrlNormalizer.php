<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Core\Util\ReceiptPayload;
use App\Entity\Sale;
use App\Entity\SaleRecord;
use App\Entity\TaxDocument;
use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class PublicReceiptUrlNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string ALREADY_CALLED = 'public_receipt_url_normalizer_called';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof TaxDocument || $data instanceof Sale || $data instanceof SaleRecord;
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $context[self::ALREADY_CALLED] = true;
        /** @var array<string, mixed> $normalized */
        $normalized = $this->normalizer->normalize($data, $format, $context);

        $payload = $this->buildPayload($data);
        if ($payload !== null) {
            $normalized['publicReceiptUrl'] = $this->urlGenerator->generate(
                'receipt_show',
                ['payload' => $payload],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        return $normalized;
    }


    private function buildPayload(mixed $object): ?string
    {
        if ($object instanceof TaxDocument) {
            return ReceiptPayload::encode($object->getDocumentType(), $object->getId());
        }

        if ($object instanceof Sale) {
            $invoice = $object->getTaxDocument();
            if ($invoice === null) {
                return null;
            }

            return ReceiptPayload::encode($invoice->getDocumentType(), $invoice->getId());
        }

        if ($object instanceof SaleRecord) {
            $taxDocumentId = $object->getTaxDocumentId();
            $documentType  = $object->getDocumentType();
            if ($taxDocumentId === null || $documentType === null) {
                return null;
            }

            return ReceiptPayload::encode($documentType, $taxDocumentId);
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getSupportedTypes(?string $format): array
    {
        return [TaxDocument::class => false, Sale::class => false, SaleRecord::class => false];
    }
}
