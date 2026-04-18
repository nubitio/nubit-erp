<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Read-only entity mapping the sale_payments table.
 * Used for DQL subqueries (e.g. filtering SaleRecord by paymentMethod).
 */
#[ORM\Entity]
#[ORM\Table(name: 'sale_payments')]
class SalePayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SaleRecord::class)]
    #[ORM\JoinColumn(name: 'sale_id', referencedColumnName: 'id', nullable: false)]
    /** @phpstan-ignore property.onlyRead */
    private SaleRecord $saleRecord;

    #[ORM\ManyToOne(targetEntity: PaymentMethod::class)]
    #[ORM\JoinColumn(name: 'payment_method_id', referencedColumnName: 'id', nullable: false)]
    /** @phpstan-ignore property.onlyRead */
    private PaymentMethod $paymentMethod;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaleRecord(): SaleRecord
    {
        return $this->saleRecord;
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }
}
