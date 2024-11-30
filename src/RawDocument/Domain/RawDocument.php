<?php

namespace App\RawDocument\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class RawDocument
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('type')]
        public ?string $type = null,
        #[SerializedName('document')]
        public ?string $document = null,
        #[SerializedName('created_at')]
        public ?\DateTimeInterface $created_at = null,
    ) {
    }
}
