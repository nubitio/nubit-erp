<?php

namespace App\Entity;

use App\Repository\CashClosureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CashClosureRepository::class)]
class CashClosure
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?CashShift $cashShift = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?PaymentMethod $paymentMethod = null;

    #[ORM\Column]
    private ?float $expectedAmount = null;

    #[ORM\Column]
    private ?float $countedAmount = null;

    #[ORM\Column]
    private ?float $difference = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCashShift(): ?CashShift
    {
        return $this->cashShift;
    }

    public function setCashShift(?CashShift $cashShift): static
    {
        $this->cashShift = $cashShift;

        return $this;
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?PaymentMethod $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getExpectedAmount(): ?float
    {
        return $this->expectedAmount;
    }

    public function setExpectedAmount(float $expectedAmount): static
    {
        $this->expectedAmount = $expectedAmount;

        return $this;
    }

    public function getCountedAmount(): ?float
    {
        return $this->countedAmount;
    }

    public function setCountedAmount(float $countedAmount): static
    {
        $this->countedAmount = $countedAmount;

        return $this;
    }

    public function getDifference(): ?float
    {
        return $this->difference;
    }

    public function setDifference(float $difference): static
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
}
