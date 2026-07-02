<?php

declare(strict_types=1);

namespace App\Billing\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'invoice:rejected:replace',
    description: 'Create replacement invoices for SUNAT-rejected invoices from a given issue date',
)]
final class ReplaceRejectedInvoicesCommand extends PerTenantCommand
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
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('date', null, InputOption::VALUE_REQUIRED, 'Issue date to scan (YYYY-MM-DD)');
        $this->addOption('execute', null, InputOption::VALUE_NONE, 'Actually create replacement documents');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum replacements to create', '0');
        $this->addOption(
            'issue-date-mode',
            null,
            InputOption::VALUE_REQUIRED,
            'Replacement issue date: original or today',
            'original',
        );
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $date = (string) $input->getOption('date');
        $execute = (bool) $input->getOption('execute');
        $limit = max(0, (int) $input->getOption('limit'));
        $issueDateMode = (string) $input->getOption('issue-date-mode');

        if (!$this->isValidDate($date)) {
            $io->error('Provide --date in YYYY-MM-DD format.');

            return Command::FAILURE;
        }

        if (!in_array($issueDateMode, ['original', 'today'], true)) {
            $io->error('Invalid --issue-date-mode. Use original or today.');

            return Command::FAILURE;
        }

        $rejected = $this->findRejectedDocuments($date, $limit);
        if ($rejected === []) {
            $io->success(sprintf('No rejected invoices found for %s.', $date));

            return Command::SUCCESS;
        }

        $io->title(sprintf('Rejected invoice replacements for %s', $date));
        $io->table(['ID', 'Type', 'Current Number', 'SUNAT Status', 'Already Replaced'], array_map(
            static fn(array $row): array => [
                (string) $row['tax_document_id'],
                (string) $row['document_type'],
                (string) $row['series'] . '-' . (string) $row['number'],
                (string) $row['sunat_status'],
                (int) $row['replacement_events'] > 0 ? 'yes' : 'no',
            ],
            $rejected,
        ));

        if (!$execute) {
            $io->warning('Dry-run only. Re-run with --execute to create replacement documents.');

            return Command::SUCCESS;
        }

        $created = [];
        $skipped = [];
        $this->connection->beginTransaction();

        try {
            foreach ($rejected as $row) {
                if ((int) $row['replacement_events'] > 0) {
                    $skipped[] = [(string) $row['tax_document_id'], 'replacement already exists'];
                    continue;
                }

                $replacement = $this->createReplacement($row, $issueDateMode);
                $created[] = [
                    $replacement['old_id'],
                    $replacement['old_number'],
                    $replacement['new_id'],
                    $replacement['new_number'],
                ];
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            $this->logger->error('Failed to create SUNAT replacement invoices', [
                'tenant' => $this->currentTenantName,
                'date' => $date,
                'exception' => $exception,
            ]);
            $io->error('Failed to create replacements: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        if ($created !== []) {
            $io->section('Created replacements');
            $io->table(['Old ID', 'Old Number', 'New ID', 'New Number'], $created);
        }

        if ($skipped !== []) {
            $io->section('Skipped');
            $io->table(['ID', 'Reason'], $skipped);
        }

        $io->success(sprintf('Created %d replacement invoice(s).', count($created)));

        return Command::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findRejectedDocuments(string $date, int $limit): array
    {
        $sql = "
            SELECT td.*,
              (
                SELECT COUNT(1)
                FROM tax_document_event e
                WHERE e.tax_document_id = td.tax_document_id
                  AND e.event_type = 'sunat_replacement_created'
              ) AS replacement_events
            FROM tax_document td
            WHERE td.document_type IN ('01', '03')
              AND DATE(td.issue_date) = :issue_date
              AND td.sunat_status REGEXP '^[0-9]+$'
              AND CAST(td.sunat_status AS UNSIGNED) BETWEEN 2000 AND 3999
            ORDER BY td.issue_date ASC, td.series ASC, CAST(td.number AS UNSIGNED) ASC
        ";

        $params = ['issue_date' => $date];
        $types = [];

        if ($limit > 0) {
            $sql .= ' LIMIT :limit';
            $params['limit'] = $limit;
            $types['limit'] = ParameterType::INTEGER;
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);

        return $rows;
    }

    /**
     * @param array<string, mixed> $old
     * @return array{old_id:string,old_number:string,new_id:string,new_number:string}
     */
    private function createReplacement(array $old, string $issueDateMode): array
    {
        $oldId = (string) $old['tax_document_id'];
        $newId = (string) $this->connection->fetchOne('SELECT UUID()');
        $newNumber = $this->nextNumber((string) $old['document_type'], (string) $old['series']);
        $newIssueDate = $issueDateMode === 'today' ? date('Y-m-d H:i:s') : (string) $old['issue_date'];

        $document = $this->buildReplacementDocumentRow($old, $newId, (string) $newNumber, $newIssueDate);
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
    private function buildReplacementDocumentRow(array $old, string $newId, string $newNumber, string $issueDate): array
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
        $lineColumns = array_filter(
            self::LINE_COLUMNS,
            static fn(string $column): bool => $column !== 'tax_document_id',
        );
        $selectColumns = implode(', ', array_map(
            static fn(string $column): string => '`' . $column . '`',
            $lineColumns,
        ));
        $insertColumns = '`tax_document_id`, ' . $selectColumns;

        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO tax_document_line (%s) SELECT :new_id, %s FROM tax_document_line WHERE tax_document_id = :old_id ORDER BY line_number',
                $insertColumns,
                $selectColumns,
            ),
            [
                'new_id' => $newId,
                'old_id' => $oldId,
            ],
        );
    }

    private function isValidDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed instanceof \DateTimeImmutable && $parsed->format('Y-m-d') === $date;
    }
}
