<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Core\Util\ReceiptPayload;
use App\Entity\CreditNote;
use App\Entity\Invoice;
use App\Entity\Sale;
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

        return $data instanceof Invoice || $data instanceof Sale || $data instanceof CreditNote;
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
        if ($object instanceof Invoice) {
            return ReceiptPayload::encode($object->getVoucherTypeId(), $object->getSaleId());
        }

        if ($object instanceof Sale) {
            $invoice = $object->getInvoiceEntity();
            if ($invoice === null) {
                return null;
            }

            return ReceiptPayload::encode($invoice->getVoucherTypeId(), $invoice->getSaleId());
        }

        if ($object->getId() === null) {
            return null;
        }

        return ReceiptPayload::encode($object->getTipoId(), $object->getId());
    }

    /**
     * @inheritdoc
     */
    public function getSupportedTypes(?string $format): array
    {
        return [Invoice::class => false, Sale::class => false, CreditNote::class => false];
    }
}
