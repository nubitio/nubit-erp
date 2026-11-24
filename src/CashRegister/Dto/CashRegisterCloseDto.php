<?php

declare(strict_types=1);

namespace App\CashRegister\Dto;

class CashRegisterCloseDto
{
    public ?string $expectedAmount = null;

    public ?string $countedAmount = null;

    public ?string $difference = null;

    public ?string $note = null;

    /**
     * How the cash was counted: denomination in cents => quantity. Absent when the total was typed.
     *
     * @var array<int|string, int>|null
     */
    public ?array $denominations = null;

    /** Whether the opening fund stays in the drawer for the next shift. */
    public ?bool $fundKept = null;

    /** @var array<array<string, string>> $closureItems */
    public array $closureItems = [];
}
