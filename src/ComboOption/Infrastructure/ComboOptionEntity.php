<?php

namespace App\ComboOption\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'combo_option')]
class ComboOptionEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\Column(name: '_key', type: 'string')]
        public ?string $_key = null,
        #[ORM\Column(name: '_value', type: 'string')]
        public ?string $_value = null,
        #[ORM\Column(name: 'active', type: 'boolean')]
        public ?bool $active = null,
    ) {
    }
}
