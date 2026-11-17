<?php

declare(strict_types=1);

namespace App\Integration\Fiscal;

/**
 * One taxed (gravado, 18% IGV) line of an integration document. The caller
 * supplies the net, tax and gross amounts in minor units so the line totals
 * add up to the document totals exactly; this channel never recomputes tax.
 */
final readonly class DocumentLine
{
    public function __construct(
        public string $description,
        public int $quantity,
        public int $subtotalMinor,
        public int $taxMinor,
        public int $totalMinor,
    ) {}
}
