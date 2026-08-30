<?php

declare(strict_types=1);

namespace App\Integration\Fiscal;

use App\Billing\Entity\TaxDocument;
use App\Integration\Entity\DocumentInputSummary;
use App\Integration\Entity\IntegrationDocument;
use App\Integration\Message\SendIntegrationDocumentMessage;
use App\Integration\Repository\IntegrationDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Turns a validated {@see DocumentInput} into a persisted {@see TaxDocument} +
 * {@see IntegrationDocument} and queues it for SUNAT. Idempotent on
 * `external_id`: a replay of the exact same payload returns the existing
 * record; a replay with a different payload is a conflict.
 *
 * Runs inside the HTTP request on the tenant connection the API key resolved,
 * so the rows land in the right database.
 */
final readonly class DocumentIntake
{
    public function __construct(
        private IntegrationDocumentRepository $documents,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private TenantContext $tenantContext,
    ) {}

    /**
     * @param array<string, mixed> $rawBody the exact request body, stored for the worker
     */
    public function submit(DocumentInput $input, array $rawBody): IntegrationDocument
    {
        $hash = self::hash($input);

        $existing = $this->documents->findOneByExternalId($input->externalId);
        if (null !== $existing) {
            if (!hash_equals($existing->getRequestHash(), $hash)) {
                throw new IdempotencyConflict($input->externalId);
            }

            return $existing;
        }

        $taxDocument = $this->buildTaxDocument($input);
        $this->entityManager->persist($taxDocument);
        $this->entityManager->flush();

        $document = new IntegrationDocument(
            $input->externalId,
            self::newReference(),
            $hash,
            $rawBody,
            new DocumentInputSummary($input->documentType, $input->series, $input->number),
        );
        $document->linkTaxDocument((string) $taxDocument->getId());

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $this->messageBus->dispatch(
            new SendIntegrationDocumentMessage(
                (string) $document->getId(),
                (string) ($this->tenantContext->getTenantName() ?? 'unknown'),
            ),
            [new DispatchAfterCurrentBusStamp()],
        );

        return $document;
    }

    private function buildTaxDocument(DocumentInput $input): TaxDocument
    {
        $taxable = self::minor($input->subtotalMinor);
        $tax = self::minor($input->taxMinor);
        $total = self::minor($input->totalMinor);

        return new TaxDocument()
            ->setDocumentType($input->sunatDocumentType())
            ->setSeries($input->series)
            ->setNumber((string) $input->number)
            ->setCurrency($input->currency)
            ->setIssueDate(new \DateTimeImmutable('now', new \DateTimeZone('America/Lima')))
            ->setPaymentMethod(1)
            ->setOperationType('0101')
            ->setSubtotal($taxable)
            ->setTaxableAmount($taxable)
            ->setIgvAmount($tax)
            ->setTotal($total)
            ->setSunatStatus(null)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());
    }

    private static function hash(DocumentInput $input): string
    {
        return hash('sha256', json_encode([
            $input->externalId,
            $input->documentType,
            $input->series,
            $input->number,
            $input->currency,
            $input->subtotalMinor,
            $input->taxMinor,
            $input->totalMinor,
            $input->customerDocType,
            $input->customerDocNumber,
            $input->customerName,
        ], JSON_THROW_ON_ERROR));
    }

    private static function newReference(): string
    {
        return sprintf('efact-%s-%s', date('Ymd'), bin2hex(random_bytes(4)));
    }

    private static function minor(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
