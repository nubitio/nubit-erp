<?php

namespace App\RawDocument\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'raw_document')]
class RawDocumentEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\Column(name: 'type', type: 'string')]
        public ?string $type = null,
        #[ORM\Column(name: 'document', type: 'text')]
        public ?string $document = null,
        #[ORM\Column(name: 'created_at', type: 'datetime')]
        public ?\DateTimeInterface $created_at = null
    ) {
    }
}
