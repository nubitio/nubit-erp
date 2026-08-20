<?php

declare(strict_types=1);

namespace App\CashRegister\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\CashRegister\Dto\CashSessionClosureSummaryDto;
use App\CashRegister\Dto\CashSessionXReportDto;
use App\CashRegister\Enum\CashShiftStatus;
use App\CashRegister\State\CashSessionClosureSummaryProvider;
use App\CashRegister\State\CashSessionMovementsProvider;
use App\CashRegister\State\CashSessionXReportProvider;
use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Not a Doctrine entity — kept only as the `fromClass` anchor for the three
 * `/cash-shifts/{id}/...` sub-resource routes below, which resolve a legacy
 * shift id to its v2 `cash_session` via `cash_sessions.legacy_cash_shift_id`
 * (a plain lookup column, never an FK) and therefore still work correctly.
 * The base CRUD operations this resource used to expose
 * (GetCollection/Post/Get/Patch/Delete via the default ORM provider) were
 * removed when the underlying `cash_shift` table was dropped 2026-08-19 —
 * see ADR-004's amendment.
 */
#[ApiResource(operations: [
    new GetCollection(
        uriTemplate: '/cash-shifts/{id}/movements',
        uriVariables: [
            'id' => new Link(fromProperty: 'id', fromClass: CashShift::class),
        ],
        paginationEnabled: false,
        normalizationContext: ['groups' => ['cash_movement:read']],
        output: CashMovement::class,
        provider: CashSessionMovementsProvider::class,
    ),
    new GetCollection(
        uriTemplate: '/cash-shifts/{id}/summary',
        paginationEnabled: false,
        normalizationContext: ['groups' => ['cash_closure_summary:read']],
        output: CashSessionClosureSummaryDto::class,
        provider: CashSessionClosureSummaryProvider::class,
    ),
    new Get(
        uriTemplate: '/cash-shifts/{id}/x-report',
        paginationEnabled: false,
        normalizationContext: ['groups' => ['cash_x_report:read']],
        output: CashSessionXReportDto::class,
        provider: CashSessionXReportProvider::class,
    ),
], mercure: true)]
class CashShift
{
    #[Groups(['cash_shift:read'])]
    // @phpstan-ignore property.unusedType (class is never instantiated — kept only as the sub-resource Link/fromClass anchor, see class docblock)
    private ?int $id = null;

    #[Groups(['cash_shift:read'])]
    #[ApiProperty(description: 'cash_shift.cash_register.label')]
    private CashRegister $cashRegister;

    #[Groups(['cash_shift:read'])]
    #[ApiProperty(description: 'cash_shift.status.label')]
    private CashShiftStatus $status;

    #[Groups(['cash_shift:read'])]
    #[ApiProperty(description: 'cash_shift.opening_balance.label')]
    private string $openingBalance = '0.00';

    #[Groups(['cash_shift:read'])]
    #[ApiProperty(description: 'cash_shift.closing_balance.label')]
    private ?string $closingBalance = null;

    #[Groups(['cash_shift:read'])]
    #[ApiProperty(description: 'cash_shift.opened_by.label')]
    private User $openedBy;

    #[Groups(['cash_shift:read'])]
    #[ApiProperty(description: 'cash_shift.closed_by.label')]
    private ?User $closedBy = null;

    #[Groups(['cash_shift:read'])]
    #[ApiProperty(description: 'cash_shift.opened_at.label')]
    private DateTimeImmutable $openedAt;

    #[Groups(['cash_shift:read'])]
    #[ApiProperty(description: 'cash_shift.closed_at.label')]
    private ?DateTimeImmutable $closedAt = null;

    #[Groups(['cash_shift:read'])]
    #[ApiProperty(description: 'cash_shift.expected_amount.label')]
    private ?string $expectedAmount = null;

    #[Groups(['cash_shift:read'])]
    #[ApiProperty(description: 'cash_shift.counted_amount.label')]
    private ?string $countedAmount = null;

    #[Groups(['cash_shift:read'])]
    #[ApiProperty(description: 'cash_shift.difference.label')]
    private ?string $difference = null;

    #[Groups(['cash_shift:read'])]
    #[ApiProperty(description: 'cash_shift.note.label')]
    private ?string $note = null;

    #[Groups(['cash_shift:read'])]
    #[ApiProperty(description: 'cash_shift.branch.label')]
    private ?Branch $branch = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCashRegister(): CashRegister
    {
        return $this->cashRegister;
    }

    public function setCashRegister(CashRegister $cashRegister): static
    {
        $this->cashRegister = $cashRegister;

        return $this;
    }

    public function getStatus(): CashShiftStatus
    {
        return $this->status;
    }

    public function setStatus(CashShiftStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getOpeningBalance(): string
    {
        return $this->openingBalance;
    }

    public function setOpeningBalance(string $openingBalance): static
    {
        $this->openingBalance = $openingBalance;

        return $this;
    }

    public function getClosingBalance(): ?string
    {
        return $this->closingBalance;
    }

    public function setClosingBalance(string $closingBalance): static
    {
        $this->closingBalance = $closingBalance;

        return $this;
    }

    public function getOpenedBy(): User
    {
        return $this->openedBy;
    }

    public function setOpenedBy(User $openedBy): static
    {
        $this->openedBy = $openedBy;

        return $this;
    }

    public function getClosedBy(): ?User
    {
        return $this->closedBy;
    }

    public function setClosedBy(User $closedBy): static
    {
        $this->closedBy = $closedBy;

        return $this;
    }

    public function getOpenedAt(): DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function setOpenedAt(DateTimeImmutable $openedAt): static
    {
        $this->openedAt = $openedAt;

        return $this;
    }

    public function getClosedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(DateTimeImmutable $closedAt): static
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getExpectedAmount(): ?string
    {
        return $this->expectedAmount;
    }

    public function setExpectedAmount(?string $expected_amount): static
    {
        $this->expectedAmount = $expected_amount;

        return $this;
    }

    public function getCountedAmount(): ?string
    {
        return $this->countedAmount;
    }

    public function setCountedAmount(?string $counted_amount): static
    {
        $this->countedAmount = $counted_amount;

        return $this;
    }

    public function getDifference(): ?string
    {
        return $this->difference;
    }

    public function setDifference(?string $difference): static
    {
        $this->difference = $difference;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): static
    {
        $this->branch = $branch;

        return $this;
    }
}
