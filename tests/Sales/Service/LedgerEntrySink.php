<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Sales\Entity\CustomerAccountEntry;

/**
 * Captures the rows the ledger would persist, so a unit test can assert running
 * balances without a database.
 */
final class LedgerEntrySink
{
    /** @var list<CustomerAccountEntry> */
    public array $entries = [];

    public function last(): CustomerAccountEntry
    {
        $index = array_key_last($this->entries);
        assert($index !== null, description: 'A ledger entry is required.');

        return $this->entries[$index];
    }
}
