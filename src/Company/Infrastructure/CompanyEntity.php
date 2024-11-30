<?php

namespace App\Company\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Media\Infrastructure\MediaEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'empresa')]
class CompanyEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'CUSTOM')]
        #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
        #[ORM\Column(name: 'empresa_id', type: 'string')]
        public ?string $empresa_id = null,
        #[ORM\Column(name: 'ruc', type: 'string')]
        public ?string $ruc = null,
        #[ORM\Column(name: 'razon_social', type: 'string')]
        public ?string $razon_social = null,
        #[ORM\Column(name: 'nombre_comercial', type: 'string')]
        public ?string $nombre_comercial = null,
        #[ORM\Column(name: 'ubigeo', type: 'string')]
        public ?string $ubigeo = null,
        #[ORM\Column(name: 'domicilio_fiscal', type: 'string')]
        public ?string $domicilio_fiscal = null,
        #[ORM\Column(name: 'telefono', type: 'string')]
        public ?string $telefono = null,
        #[ORM\ManyToOne(targetEntity: MediaEntity::class)]
        #[ORM\JoinColumn(name: 'logo', referencedColumnName: 'id')]
        public ?MediaEntity $logo = null,
        #[ORM\Column(name: 'certificado', type: 'string')]
        public ?string $certificado = null,
        #[ORM\Column(name: 'sunat_user', type: 'string')]
        public ?string $sunat_user = null,
        #[ORM\Column(name: 'sunat_password', type: 'string')]
        public ?string $sunat_password = null,
        #[ORM\Column(name: 'estado', type: 'boolean')]
        public ?bool $estado = null,
    ) {
    }
}
