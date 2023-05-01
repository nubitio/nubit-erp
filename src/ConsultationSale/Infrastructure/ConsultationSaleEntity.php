<?php

namespace App\ConsultationSale\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: "consultation_sale")]
class ConsultationSaleEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\Column(name: 'consultation_id', type: 'integer')]
        public ?int $consultation_id = null,
        #[ORM\Column(name: 'sale_id', type: 'integer')]
        public ?int $sale_id = null
    )
    {
    }
}