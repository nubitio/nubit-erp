<?php

declare(strict_types=1);

namespace App\Sales\Report\CustomerStatement;

use Doctrine\DBAL\Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds a {@see CustomerStatement} for a customer over a date range (#366).
 */
final readonly class CustomerStatementService
{
    public function __construct(
        private StatementRepository $repository,
        private StatementAssembler $assembler,
    ) {}

    /**
     * @throws Exception
     */
    public function build(int $customerId, string $dateFrom, string $dateTo, string $branchId): CustomerStatement
    {
        $customer = $this->repository->findCustomer($customerId);
        if ($customer === null) {
            throw new NotFoundHttpException(sprintf('Cliente %d no encontrado.', $customerId));
        }

        $applications = $this->repository->crossBranchAdvanceApplications($customerId, $dateTo, $branchId);

        return $this->assembler->assemble(
            $customerId,
            $customer['name'],
            $customer['doc'],
            $dateFrom,
            $dateTo,
            [...$this->repository->charges($customerId, $dateTo, $branchId), ...$applications['charges']],
            [...$this->repository->payments($customerId, $dateTo, $branchId), ...$applications['payments']],
        );
    }

    /**
     * Flatten a statement into rows for the XLSX exporter: the running ledger
     * followed by a totals block and the aging breakdown.
     *
     * @return list<array<string, string>>
     */
    public function toRows(CustomerStatement $statement): array
    {
        $rows = [[
            'date' => $statement->dateFrom,
            'type' => 'Saldo inicial',
            'document' => '',
            'detail' => sprintf('Período %s a %s', $statement->dateFrom, $statement->dateTo),
            'charge' => '',
            'payment' => '',
            'balance' => $statement->openingBalance,
        ]];

        foreach ($statement->lines as $line) {
            $rows[] = [
                'date' => $line->date,
                'type' => $line->type,
                'document' => $line->document,
                'detail' => $line->detail,
                'charge' => $line->charge === '0.00' ? '' : $line->charge,
                'payment' => $line->payment === '0.00' ? '' : $line->payment,
                'balance' => $line->balance,
            ];
        }

        $rows[] = [
            'date' => '',
            'type' => 'Totales',
            'document' => '',
            'detail' => '',
            'charge' => $statement->totalCharges,
            'payment' => $statement->totalPayments,
            'balance' => $statement->closingBalance,
        ];

        foreach ($statement->aging as $bucket => $amount) {
            $rows[] = [
                'date' => '',
                'type' => 'Antigüedad',
                'document' => $bucket . ' días',
                'detail' => '',
                'charge' => '',
                'payment' => '',
                'balance' => $amount,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    public function columns(): array
    {
        return [
            'date' => 'Fecha',
            'type' => 'Tipo',
            'document' => 'Documento',
            'detail' => 'Detalle',
            'charge' => 'Cargo',
            'payment' => 'Abono',
            'balance' => 'Saldo',
        ];
    }
}
