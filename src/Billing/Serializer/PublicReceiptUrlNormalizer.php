<?php

declare(strict_types=1);

namespace App\Billing\Serializer;

use App\Billing\Receipt\ReceiptPayload;
use App\Sales\Entity\SaleRecord;
use App\Billing\Entity\TaxDocument;
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

        return $data instanceof TaxDocument || $data instanceof SaleRecord;
    }

    /**
     * @inheritdoc
     *
     * @return array<string, mixed>
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
        return [TaxDocument::class => false, SaleRecord::class => false];
    }
}
