<?php

namespace App\Performance\Infrastructure;

use App\ComboOption\Infrastructure\ComboOptionEntity;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Product\Infrastructure\ProductEntity;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'performance')]
class PerformanceEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\ManyToOne(targetEntity: ComboOptionEntity::class)]
        #[ORM\JoinColumn(name: 'line_fk', referencedColumnName: 'id')]
        public ?ComboOptionEntity $lines = null,
        #[ORM\ManyToOne(targetEntity: ProductEntity::class)]
        #[ORM\JoinColumn(name: 'product_fk', referencedColumnName: 'producto_id')]
        public ?ProductEntity $products = null,
        #[ORM\ManyToOne(targetEntity: ComboOptionEntity::class)]
        #[ORM\JoinColumn(name: 'type_fk', referencedColumnName: 'id')]
        public ?ComboOptionEntity $types = null,
        #[ORM\Column(name: 'date', type: 'date')]
        public ?\DateTimeInterface $date = null,
        #[ORM\Column(name: 'start', type: 'time')]
        public ?\DateTimeInterface $start = null,
        #[ORM\Column(name: 'end', type: 'time')]
        public ?\DateTimeInterface $end = null,
        #[ORM\Column(name: 'quantity', type: 'decimal')]
        public ?float $quantity = null,
        #[ORM\Column(name: 'obs', type: 'string')]
        public ?string $obs = null,
    ) {
    }
}
