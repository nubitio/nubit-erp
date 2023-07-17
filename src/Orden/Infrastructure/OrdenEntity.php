<?php

namespace App\Orden\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Mesa\Infrastructure\MesaEntity;
use App\OrdenDetail\Infrastructure\OrdenDetailEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'orden')]
class OrdenEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'orden_id', type: 'integer')]
        public ?int $orden_id = null,
        #[ORM\Column(name: 'fecha', type: 'datetime')]
        public ?\DateTimeInterface $fecha = null,
        #[ManyToOne(targetEntity: MesaEntity::class)]
        #[JoinColumn(name: 'mesa_id', referencedColumnName: 'mesa_id')]
        public ?MesaEntity $mesa = null,
        #[ORM\Column(name: 'mesa_origen', type: 'integer')]
        public ?int $mesa_origen = null,
        #[ORM\Column(name: 'nota', type: 'string')]
        public ?string $nota = null,
        #[ORM\Column(name: 'estado_cocina', type: 'integer')]
        public ?int $estado_cocina = null,
        #[ORM\Column(name: 'estado_barra', type: 'integer')]
        public ?int $estado_barra = null,
        #[ORM\Column(name: 'usuario_id', type: 'integer')]
        public ?int $usuario_id = null,
        #[ORM\Column(name: 'glosa', type: 'string')]
        public ?string $glosa = null,
        #[ORM\Column(name: 'estado', type: 'integer')]
        public ?int $estado = null,
        #[ORM\Column(name: 'anulado', type: 'boolean')]
        public ?bool $anulado = null,
        #[ORM\Column(name: 'date_created', type: 'datetime')]
        public ?\DateTimeInterface $date_created = null,
        #[ORM\Column(name: 'date_updated', type: 'datetime')]
        public ?\DateTimeInterface $date_updated = null,
        #[ORM\Column(name: 'user_create', type: 'integer')]
        public ?int $user_create = null,
        #[ORM\Column(name: 'user_update', type: 'integer')]
        public ?int $user_update = null,
        /**
         * @var OrdenDetailEntity[] $details
         *
         * @phpstan-var ArrayCollection<int , OrdenDetailEntity>|array<int , OrdenDetailEntity>
         */
        #[ORM\OneToMany(mappedBy: 'orden', targetEntity: OrdenDetailEntity::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
        public Collection|array $details = new ArrayCollection(),
    ) {
    }
}
