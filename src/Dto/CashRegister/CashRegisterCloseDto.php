<?php

namespace App\Dto\CashRegister;

class CashRegisterCloseDto
{
    public ?float $expectedAmount = null;

    public ?float $countedAmount = null;

    public ?float $difference = null;

    public ?string $note = null;

    public array $closureItems = [];
}
