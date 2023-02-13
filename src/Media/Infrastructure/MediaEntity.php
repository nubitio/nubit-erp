<?php

namespace App\Media\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: "media")]
class MediaEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'CUSTOM')]
        #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
        #[ORM\Column(name: 'id', type: 'string')]
        public ?string $id = null,
        #[ORM\Column(name: 'path', type: 'string')]
        public ?string $path = null
    )
    {
    }
}