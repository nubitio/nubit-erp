<?php

namespace App\Specialty\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'specialty')]
class SpecialtyEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\Column(name: 'name', type: 'string')]
        public ?string $name = null,
        #[ORM\Column(name: 'description', type: 'string')]
        public ?string $description = null,
    ) {
    }
}
