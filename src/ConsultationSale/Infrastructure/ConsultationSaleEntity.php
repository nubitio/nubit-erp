<?php

namespace App\ConsultationSale\Infrastructure;

use App\Consultation\Infrastructure\ConsultationEntity;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Sale\Infrastructure\SaleEntity;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'consultation_sale')]
class ConsultationSaleEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\ManyToOne(targetEntity: ConsultationEntity::class)]
        #[ORM\JoinColumn(name: 'consultation_id', referencedColumnName: 'id')]
        public ?ConsultationEntity $consultation = null,
        #[ORM\ManyToOne(targetEntity: SaleEntity::class)]
        #[ORM\JoinColumn(name: 'sale_id', referencedColumnName: 'movimiento_id')]
        public ?SaleEntity $sale = null
    ) {
    }
}
