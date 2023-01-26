<?php

namespace App\Category\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'categoria')]
class CategoryEntity
{
    public function __construct(
        #[Id]
        #[GeneratedValue(strategy: 'CUSTOM')]
        #[CustomIdGenerator(class: UuidGenerator::class)]
        #[Column(name: 'categoria_id', type: 'string')]
        public ?string         $categoria_id = null,
        #[Column(name: 'descripcion', type: 'string')]
        public ?string         $descripcion = null,
        #[ManyToOne (targetEntity: CategoryEntity::class, inversedBy: 'children')]
        #[JoinColumn(name: 'parent_id', referencedColumnName: 'categoria_id')]
        public ?CategoryEntity $parent = null,
        #[Column(name: 'imagen', type: 'string')]
        public ?string         $imagen = null,
        #[Column(name: 'estado', type: 'boolean')]
        public ?bool           $estado = false,
        #[OneToMany(mappedBy: 'parent', targetEntity: CategoryEntity::class)]
        public Collection      $children = new ArrayCollection(),
    )
    {
    }
}
