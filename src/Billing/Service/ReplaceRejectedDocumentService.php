<?php

declare(strict_types=1);

namespace App\Billing\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;

/**
 * Creates a replacement tax_document for a SUNAT-rejected one.
 *
 * Shared between ReplaceRejectedInvoicesCommand (bulk/CLI)
 * and the per-sale REST endpoint used from the UI.
 */
readonly class ReplaceRejectedDocumentService
{
    /** @var list<string> */
    private const array DOCUMENT_COLUMNS = [
        'tax_document_id',
        'movimiento_id',
        'sale_id',
        'related_tax_document_id',
        'branch_id',
        'customer_id',
        'document_type',
        'issue_date',
        'series',
        'number',
        'affected_document',
        'reason_code',
        'reason_description',
        'currency',
        'exchange_rate',
        'payment_method',
        'subtotal',
        'taxable_amount',
        'igv_amount',
        'rc_amount',
        'total',
        'discount_percent',
        'discount_amount',
        'operation_type',
        'detraction_code',
        'detraction_percent',
        'detraction_amount',
        'detraction_payment_method',
        'detraction_value_ref',
        'sunat_status',
        'sunat_message',
        'digest',
        'cdr_path',
        'void_ticket',
        'void_correlation',
        'sent_at',
        'accepted_at',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    private const array LINE_COLUMNS = [
        'tax_document_id',
        'line_number',
        'product_id',
        'product_code',
        'product_name',
        'product_uom',
        'quantity',
        'unit_price',
        'unit_value',
        'discount_amount',
        'taxed_amount',
        'tax_amount',
        'tax_percentage',
        'tax_type',
        'total',
        'created_at',
    ];

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
    ) {}

    /**
     * Creates a replacement for the most recent rejected tax_document
     * associated with the given sale (by sale_id or legacy movimiento_id).
     *
     * Returns ['success' => bool, 'message' => string, 'newId' => ?string].
     *
     * @return array{success: bool, message: string, newId: ?string}
     */
    public function replaceForSaleId(string $saleId, string $issueDateMode = 'today'): array
    {
        $rejected = $this->findRejectedForSale($saleId);

        if ($rejected === null) {
            return ['success' => false, 'message' => 'No hay comprobante rechazado para esta venta.', 'newId' => null];
        }

        if ((int) ($rejected['replacement_events'] ?? 0) > 0) {
            return [
                'success' => false,
                'message' => 'Ya existe un comprobante de reemplazo para este documento.',
                'newId' => null,
            ];
        }

        $this->connection->beginTransaction();
        try {
            $result = $this->createReplacement($rejected, $issueDateMode);
            $this->connection->commit();

            $this->logger->info('Replacement document created via UI', [
                'sale_id' => $saleId,
                'old_id' => $result['old_id'],
                'new_id' => $result['new_id'],
                'new_number' => $result['new_number'],
            ]);

            return [
                'success' => true,
                'message' => 'Comprobante de reemplazo creado: ' . $result['new_number'],
                'newId' => $result['new_id'],
            ];
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            $this->logger->error('Failed to create replacement document', [
                'sale_id' => $saleId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al crear el reemplazo: ' . $e->getMessage(),
                'newId' => null,
            ];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRejectedForSale(string $saleId): ?array
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative("
            SELECT td.*,
              (
                SELECT COUNT(1)
                FROM tax_document_event e
                WHERE e.tax_document_id = td.tax_document_id
                  AND e.event_type = 'sunat_replacement_created'
              ) AS replacement_events
            FROM tax_document td
            WHERE (td.sale_id = :id OR td.movimiento_id = :id)
              AND td.document_type IN ('01', '03')
              AND td.sunat_status REGEXP '^[0-9]+$'
              AND CAST(td.sunat_status AS UNSIGNED) BETWEEN 2000 AND 3999
            ORDER BY td.created_at DESC
            LIMIT 1
            ", ['id' => $saleId]);

        return $row !== false ? $row : null;
    }

    /**
     * @param array<string, mixed> $old
     * @return array{old_id: string, old_number: string, new_id: string, new_number: string}
     */
    public function createReplacement(array $old, string $issueDateMode = 'today'): array
    {
        $oldId = (string) $old['tax_document_id'];
        $newId = (string) $this->connection->fetchOne('SELECT UUID()');
        $newNumber = $this->nextNumber((string) $old['document_type'], (string) $old['series']);
        $newIssueDate = $issueDateMode === 'today' ? date('Y-m-d H:i:s') : (string) $old['issue_date'];

        $document = $this->buildReplacementRow($old, $newId, (string) $newNumber, $newIssueDate);
        $this->connection->insert('tax_document', $document);
        $this->copyLines($oldId, $newId);

        $oldNumber = (string) $old['series'] . '-' . (string) $old['number'];
        $newFullNumber = (string) $old['series'] . '-' . (string) $newNumber;
        $payload = json_encode([
            'old_tax_document_id' => $oldId,
            'old_number' => $oldNumber,
            'new_tax_document_id' => $newId,
            'new_number' => $newFullNumber,
        ], JSON_UNESCAPED_UNICODE);

        $this->connection->insert('tax_document_event', [
            'tax_document_id' => $oldId,
            'event_type' => 'sunat_replacement_created',
            'status' => 'replaced',
            'code' => (string) $old['sunat_status'],
            'message' => 'Replacement tax document created after SUNAT rejection',
            'payload_json' => $payload,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->connection->insert('tax_document_event', [
            'tax_document_id' => $newId,
            'event_type' => 'issued',
            'status' => 'pending',
            'code' => null,
            'message' => 'Replacement document pending SUNAT send',
            'payload_json' => $payload,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'old_id' => $oldId,
            'old_number' => $oldNumber,
            'new_id' => $newId,
            'new_number' => $newFullNumber,
        ];
    }

    private function nextNumber(string $documentType, string $series): int
    {
        return (int) $this->connection->fetchOne('
            SELECT IFNULL(MAX(CAST(number AS UNSIGNED)), 0) + 1
            FROM tax_document
            WHERE document_type = :document_type
              AND series = :series
            FOR UPDATE
            ', [
            'document_type' => $documentType,
            'series' => $series,
        ]);
    }

    /**
     * @param array<string, mixed> $old
     * @return array<string, mixed>
     */
    private function buildReplacementRow(array $old, string $newId, string $newNumber, string $issueDate): array
    {
        $row = [];
        foreach (self::DOCUMENT_COLUMNS as $column) {
            if (array_key_exists($column, $old)) {
                $row[$column] = $old[$column];
            }
        }

        $row['tax_document_id'] = $newId;
        $row['issue_date'] = $issueDate;
        $row['number'] = $newNumber;
        $row['sunat_status'] = 'pendiente';
        $row['sunat_message'] =
            'Replacement for rejected document ' . (string) $old['series'] . '-' . (string) $old['number'];
        $row['digest'] = null;
        $row['cdr_path'] = null;
        $row['void_ticket'] = null;
        $row['void_correlation'] = null;
        $row['sent_at'] = null;
        $row['accepted_at'] = null;
        $row['created_at'] = date('Y-m-d H:i:s');
        $row['updated_at'] = date('Y-m-d H:i:s');

        return $row;
    }

    private function copyLines(string $oldId, string $newId): void
    {
        $lineColumns = array_filter(self::LINE_COLUMNS, static fn(string $c): bool => $c !== 'tax_document_id');
        $selectColumns = implode(', ', array_map(static fn(string $c): string => '`' . $c . '`', $lineColumns));
        $insertColumns = '`tax_document_id`, ' . $selectColumns;

        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO tax_document_line (%s) SELECT :new_id, %s FROM tax_document_line WHERE tax_document_id = :old_id ORDER BY line_number',
                $insertColumns,
                $selectColumns,
            ),
            ['new_id' => $newId, 'old_id' => $oldId],
        );
    }
}
