<?php

declare(strict_types=1);

namespace App\Reporting\Ple;

use App\Efact\Service\CompanyContext;

final readonly class PleValidationService
{
    public function __construct(
        private SalesLedgerExporter $salesExporter,
        private PurchaseLedgerExporter $purchaseExporter,
        private PleLedgerValidator $validator,
        private CompanyContext $companyContext,
    ) {
    }

    /**
     * @param 'sales'|'purchases'|null $book
     *
     * @return array<string, mixed>
     */
    public function validate(string $period, ?string $book = null): array
    {
        $companyIssues = $this->validateCompany();
        $books = [];

        if ($book === null || $book === 'sales') {
            $salesPreview = $this->salesExporter->preview($period);
            $salesIssues = array_merge($companyIssues, $this->validator->validateSalesBook($salesPreview, $period));
            $books['sales'] = $this->bookValidation($salesIssues);
        }

        if ($book === null || $book === 'purchases') {
            $purchasePreview = $this->purchaseExporter->preview($period);
            $purchaseIssues = array_merge($companyIssues, $this->validator->validatePurchasesBook($purchasePreview, $period));
            $books['purchases'] = $this->bookValidation($purchaseIssues);
        }

        $valid = true;
        foreach ($books as $result) {
            if (!$result['valid']) {
                $valid = false;
                break;
            }
        }

        return [
            'period' => $period,
            'valid' => $valid,
            'validatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'books' => $books,
        ];
    }

    /**
     * @param list<array{severity: string, row: ?int, code: string, message: string}> $issues
     *
     * @return array{valid: bool, issueCount: int, issues: list<array{severity: string, row: ?int, code: string, message: string}>}
     */
    private function bookValidation(array $issues): array
    {
        return [
            'valid' => $issues === [],
            'issueCount' => count($issues),
            'issues' => $issues,
        ];
    }

    /**
     * @return list<array{severity: string, row: ?int, code: string, message: string}>
     */
    private function validateCompany(): array
    {
        try {
            $ruc = $this->companyContext->getId();
        } catch (\Throwable) {
            return [[
                'severity' => 'error',
                'row' => null,
                'code' => 'COMPANY_NOT_CONFIGURED',
                'message' => 'Configure los datos de la empresa antes de generar el PLE.',
            ]];
        }

        if (!preg_match('/^\d{11}$/', $ruc)) {
            return [[
                'severity' => 'error',
                'row' => null,
                'code' => 'INVALID_COMPANY_RUC',
                'message' => 'El RUC de la empresa debe tener 11 dígitos.',
            ]];
        }

        return [];
    }
}