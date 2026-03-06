<?php

declare(strict_types=1);

namespace App\Dto\CashRegister;

class CashRegisterCloseDto
{
    public ?string $expectedAmount = null;

    public ?string $countedAmount = null;

    public ?string $difference = null;

    public ?string $note = null;

    /** @var array<array<string, string>> $closureItems */
    public array $closureItems = [];
}
