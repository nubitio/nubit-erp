<?php

namespace App\Customer\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Customer\Domain\Customer;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'cliente')]
class CustomerEntity
{
    #[Id]
    #[Column(name: 'ruc', type: 'string')]
    public ?string $ruc;
    #[Column(name: 'razon_social', type: 'string')]
    public ?string $razon_social;
    #[Column(name: 'domicilio_fiscal', type: 'string')]
    public ?string $domicilio_fiscal;
    #[Column(name: 'telefono', type: 'string')]
    public ?string $telefono;
    #[Column(name: 'email', type: 'string')]
    public ?string $email;
    #[Column(name: 'imagen', type: 'string')]
    public ?string $imagen;
    #[Column(name: 'estado', type: 'boolean')]
    public ?bool $estado;

    public function mapToDomainModel(): Customer
    {
        return new Customer(
            $this->ruc,
            $this->razon_social,
            $this->domicilio_fiscal,
            $this->telefono,
            $this->email,
            $this->imagen,
            $this->estado,
        );
    }

    public function mapFromDomainModel(Customer $customer): CustomerEntity
    {
        $this->ruc = $customer->ruc;
        $this->razon_social = $customer->razon_social;
        $this->domicilio_fiscal = $customer->domicilio_fiscal;
        $this->telefono = $customer->telefono;
        $this->email = $customer->email;
        $this->imagen = $customer->imagen;
        $this->estado = $customer->estado;

        return $this;
    }
}
