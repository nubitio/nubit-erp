<?php

namespace App\Production\Infrastructure;

use App\ComboOption\Infrastructure\ComboOptionEntity;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Employee\Infrastructure\EmployeeEntity;
use App\Product\Infrastructure\ProductEntity;
use App\ProductionDetail\Infrastructure\ProductionDetailEntity;
use App\Recipe\Infrastructure\RecipeEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'production')]
class ProductionEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\ManyToOne(targetEntity: ProductEntity::class)]
        #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'producto_id')]
        public ?ProductEntity $product = null,
        #[ORM\Column(name: 'scheduled_date', type: 'datetime')]
        public ?\DateTimeInterface $scheduled_date = null,
        #[ORM\Column(name: 'quantity', type: 'decimal')]
        public ?float $quantity = null,
        #[ORM\ManyToOne(targetEntity: RecipeEntity::class)]
        #[ORM\JoinColumn(name: 'recipe_id', referencedColumnName: 'id')]
        public ?RecipeEntity $recipe = null,
        #[ORM\ManyToOne(targetEntity: EmployeeEntity::class)]
        #[ORM\JoinColumn(name: 'employee_id', referencedColumnName: 'id')]
        public ?EmployeeEntity $employee = null,
        #[ORM\ManyToOne(targetEntity: ComboOptionEntity::class)]
        #[ORM\JoinColumn(name: 'production_line_id', referencedColumnName: 'id')]
        public ?ComboOptionEntity $production_line = null,
        #[ORM\Column(name: 'status', type: 'integer')]
        public ?int $status = null,
        /**
         * @var ProductionDetailEntity[] $details
         *
         * @phpstan-var ArrayCollection<int , ProductionDetailEntity>|array<int , ProductionDetailEntity>
         */
        #[ORM\OneToMany(mappedBy: 'production', targetEntity: ProductionDetailEntity::class, cascade: ['persist', 'remove'])]
        public Collection|array $details = new ArrayCollection(),
    ) {
    }
}
