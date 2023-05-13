<?php

namespace App\Customer\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\PriceList\Infrastructure\PriceListEntity;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'cliente')]
class CustomerEntity
{
    public function __construct(
        #[Id]
        #[Column(name: 'ruc', type: 'string')]
        public ?string $ruc = null,
        #[Column(name: 'razon_social', type: 'string')]
        public ?string $razon_social = null,
        #[Column(name: 'domicilio_fiscal', type: 'string')]
        public ?string $domicilio_fiscal = null,
        #[Column(name: 'telefono', type: 'string')]
        public ?string $telefono = null,
        #[Column(name: 'email', type: 'string')]
        public ?string $email = null,
        #[Column(name: 'imagen', type: 'string')]
        public ?string $imagen = null,
        #[ManyToOne(targetEntity: PriceListEntity::class)]
        #[JoinColumn(name: 'price_list_id', referencedColumnName: 'id')]
        public ?PriceListEntity $price_list = null,
        #[Column(name: 'estado', type: 'boolean')]
        public ?bool $estado = null
    ) {
    }
}
