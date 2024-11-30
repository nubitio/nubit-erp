<?php

namespace App\ProductionDetail\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Product\Infrastructure\ProductEntity;
use App\Production\Infrastructure\ProductionEntity;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'production_detail')]
class ProductionDetailEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\Column(name: 'production_id', type: 'integer')]
        public ?int $production_id = null,
        #[ORM\ManyToOne(targetEntity: ProductEntity::class)]
        #[ORM\JoinColumn(name: 'material_id', referencedColumnName: 'producto_id')]
        public ?ProductEntity $material = null,
        #[ORM\Column(name: 'quantity', type: 'decimal')]
        public ?float $quantity = null,
        #[ORM\ManyToOne(targetEntity: ProductionEntity::class, inversedBy: 'details')]
        #[ORM\JoinColumn(name: 'production_id', referencedColumnName: 'id')]
        public ?ProductionEntity $production = null,
    ) {
    }
}
