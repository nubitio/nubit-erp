<?php

declare(strict_types=1);

namespace App\Accounting\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'journal_entry_line')]
class JournalEntryLine
{
    #[Groups(['journal_entry:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: JournalEntry::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'journal_entry_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private JournalEntry $entry;

    #[Groups(['journal_entry:read'])]
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'account_id', referencedColumnName: 'id', nullable: false)]
    private Account $account;

    #[Groups(['journal_entry:read'])]
    #[ORM\Column(name: 'line_no', type: Types::INTEGER)]
    private int $lineNo = 1;

    #[Groups(['journal_entry:read'])]
    #[ORM\Column(name: 'debit', type: Types::DECIMAL, precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $debit = '0.00';

    #[Groups(['journal_entry:read'])]
    #[ORM\Column(name: 'credit', type: Types::DECIMAL, precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $credit = '0.00';

    #[Groups(['journal_entry:read'])]
    #[ORM\Column(name: 'memo', length: 255, nullable: true)]
    private ?string $memo = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEntry(): ?JournalEntry
    {
        return $this->entry ?? null;
    }

    public function setEntry(JournalEntry $entry): static
    {
        $this->entry = $entry;

        return $this;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getLineNo(): int
    {
        return $this->lineNo;
    }

    public function setLineNo(int $lineNo): static
    {
        $this->lineNo = $lineNo;

        return $this;
    }

    public function getDebit(): string
    {
        return $this->debit;
    }

    public function setDebit(string $debit): static
    {
        $this->debit = $debit;

        return $this;
    }

    public function getCredit(): string
    {
        return $this->credit;
    }

    public function setCredit(string $credit): static
    {
        $this->credit = $credit;

        return $this;
    }

    public function getMemo(): ?string
    {
        return $this->memo;
    }

    public function setMemo(?string $memo): static
    {
        $this->memo = $memo;

        return $this;
    }
}
