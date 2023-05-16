<?php

namespace App\Recipe\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\RecipeDetail\Infrastructure\RecipeDetailEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'recipe')]
class RecipeEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\Column(name: 'name', type: 'string')]
        public ?string $name = null,
        #[ORM\Column(name: 'active', type: 'boolean')]
        public ?bool $active = null,
        /**
         * @var RecipeDetailEntity[] $details
         *
         * @phpstan-var ArrayCollection<int , RecipeEntity>|array<int , RecipeEntity>
         */
        #[ORM\OneToMany(mappedBy: 'recipe', targetEntity: RecipeDetailEntity::class, cascade: ['persist', 'remove'])]
        public Collection|array $details = new ArrayCollection(),
    ) {
    }
}
