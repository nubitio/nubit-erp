<?php

declare(strict_types=1);

namespace App\BankReconciliation\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

final class BankStatementLineSuggestionDto
{
    public function __construct(
        #[Groups(['bank_reconciliation_suggestion:read'])]
        public string $sourceType,
        #[Groups(['bank_reconciliation_suggestion:read'])]
        public int|string $sourceId,
        #[Groups(['bank_reconciliation_suggestion:read'])]
        public string $amount,
        #[Groups(['bank_reconciliation_suggestion:read'])]
        public string $date,
        #[Groups(['bank_reconciliation_suggestion:read'])]
        public string $label,
        #[Groups(['bank_reconciliation_suggestion:read'])]
        public string $confidence,
    ) {}
}
