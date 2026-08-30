<?php

declare(strict_types=1);

namespace App\Integration\Fiscal;

/**
 * The normalised result of one SUNAT submission, distilled from greenter's
 * {@see \Greenter\Model\Response\BillResult}.
 */
final readonly class SunatOutcome
{
    public function __construct(
        public bool $accepted,
        public ?string $sunatStatus,
        public ?string $cdrReference,
        public ?string $message,
    ) {}
}
