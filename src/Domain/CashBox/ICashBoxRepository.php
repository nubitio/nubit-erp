<?php

namespace App\Domain\CashBox;

interface ICashBoxRepository
{
    public function getInfoReceiptType(string $cashBoxId): array;
}
