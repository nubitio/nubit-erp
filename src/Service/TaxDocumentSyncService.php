<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final readonly class TaxDocumentSyncService
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @throws Exception
     */
    public function recordSunatEvent(
        string $documentType,
        string $series,
        string $number,
        string $eventType,
        ?string $statusCode,
        ?string $statusMessage,
        ?string $digest = null,
        ?array $payload = null,
    ): void {
        $taxDocumentId = $this->findTaxDocumentByDocument(
            $documentType,
            $series,
            $number
        );
        if ($taxDocumentId === null) {
            return;
        }

        $normalizedCode = strtolower(trim((string)$statusCode));
        $accepted = in_array($normalizedCode, ['0', 'aceptado', 'accepted'], true);
        $pending = $normalizedCode === '' || $normalizedCode === 'pendiente';

        $this->connection->update('tax_document', [
            'sunat_status' => $statusCode,
            'sunat_message' => $statusMessage,
            'digest' => $digest,
            'sent_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
            'accepted_at' => $accepted ? new DateTimeImmutable()->format('Y-m-d H:i:s') : null,
            'updated_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
        ], [
            'tax_document_id' => $taxDocumentId,
        ]);

        $this->connection->insert('tax_document_event', [
            'tax_document_id' => $taxDocumentId,
            'event_type' => $eventType,
            'status' => $pending ? 'pending' : ($accepted ? 'accepted' : 'error'),
            'code' => $statusCode,
            'message' => $statusMessage,
            'payload_json' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @throws Exception
     */
    private function findTaxDocumentByDocument(string $documentType, string $series, string $number): ?string
    {
        $taxDocumentId = $this->connection->fetchOne(
            'SELECT tax_document_id
             FROM tax_document
             WHERE document_type = :documentType
               AND series = :series
               AND number = :number
             LIMIT 1',
            [
                'documentType' => $documentType,
                'series' => $series,
                'number' => $number,
            ]
        );

        return is_string($taxDocumentId) ? $taxDocumentId : null;
    }
}
