<?php

namespace App\UnitOfMeasure\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'unidad')]
class UnitOfMeasureEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'unidad_id', type: 'integer')]
        public ?int $unidad_id = null,
        #[ORM\Column(name: 'name', type: 'string')]
        public ?string $name = null,
        #[ORM\Column(name: 'code', type: 'string')]
        public ?string $code = null,
        #[ORM\Column(name: 'type_id', type: 'integer')]
        public ?int $type_id = null,
        #[ORM\Column(name: 'ratio', type: 'decimal')]
        public ?float $ratio = null,
        #[ORM\Column(name: 'is_reference', type: 'boolean')]
        public ?bool $is_reference = null,
        #[ORM\Column(name: 'active', type: 'boolean')]
        public ?bool $active = null,
    ) {
    }
}
