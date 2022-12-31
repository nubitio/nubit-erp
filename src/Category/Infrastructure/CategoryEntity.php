<?php

namespace App\Category\Infrastructure;

use App\Category\Domain\Category;
use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'categoria')]
class CategoryEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'categoria_id', type: 'string')]
    public ?string $categoria_id;
    #[Column(name: 'descripcion', type: 'string')]
    public ?string $descripcion;
    #[Column(name: 'parent_id', type: 'string')]
    public ?string $parent_id;
    #[Column(name: 'imagen', type: 'string')]
    public ?string $imagen;
    #[Column(name: 'estado', type: 'boolean')]
    public ?bool $estado;

    public function mapToDomainModel(): Category
    {
        return new Category(
            $this->categoria_id,
            $this->descripcion,
            $this->parent_id,
            $this->imagen,
            $this->estado,
        );
    }

    public function mapFromDomainModel(Category $category): CategoryEntity
    {
        $this->categoria_id = $category->categoria_id;
        $this->descripcion = $category->descripcion;
        $this->parent_id = $category->parent_id;
        $this->imagen = $category->imagen;
        $this->estado = $category->estado;

        return $this;
    }
}
