<?php

namespace App\User\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class User
{
    public function __construct(
        #[SerializedName('usuario_id')]
        public ?int $usuario_id,
        #[SerializedName('username')]
        public ?string $username,
        #[SerializedName('password')]
        public ?string $password,
        #[SerializedName('estado')]
        public ?bool $estado,
        #[SerializedName('date_created')]
        public ?\DateTime $date_created,
        #[SerializedName('date_updated')]
        public ?\DateTime $date_updated,
        #[SerializedName('pin')]
        public ?string $pin,
        #[SerializedName('rol_id')]
        public ?int $rol_id,

    )
    {
    }
}