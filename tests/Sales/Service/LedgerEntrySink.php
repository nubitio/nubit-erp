<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Sales\Entity\CustomerAccountEntry;
use LogicException;

/** Captures ledger rows in unit tests. */
final class LedgerEntrySink
{
    /** @var list<CustomerAccountEntry> */
    public array $entries = [];

    public function last(): CustomerAccountEntry
    {
        $key = array_key_last($this->entries);
        if ($key === null) {
            throw new LogicException('Cannot read the last entry from an empty sink.');
        }

        return $this->entries[$key];
    }
}
