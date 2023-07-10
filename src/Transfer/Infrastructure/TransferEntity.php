<?php

namespace App\Transfer\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'transfer')]
class TransferEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'transfer_id', type: 'string')]
        public ?string $transfer_id = null,
        #[ORM\Column(name: 'fecha', type: 'date')]
        public ?\DateTimeInterface $fecha = null,
        #[ORM\Column(name: 'almacen_origen', type: 'string')]
        public ?string $almacen_origen = null,
        #[ORM\Column(name: 'almacen_destino', type: 'string')]
        public ?string $almacen_destino = null,
        #[ORM\Column(name: 'nota', type: 'string')]
        public ?string $nota = null,
        #[ORM\Column(name: 'salida_id', type: 'string')]
        public ?string $salida_id = null,
        #[ORM\Column(name: 'ingreso_id', type: 'string')]
        public ?string $ingreso_id = null,
        #[ORM\Column(name: 'created_by', type: 'integer')]
        public ?int $created_by = null,
        #[ORM\Column(name: 'updated_by', type: 'integer')]
        public ?int $updated_by = null,
        #[ORM\Column(name: 'created_at', type: 'datetime')]
        public ?\DateTimeInterface $created_at = null,
        #[ORM\Column(name: 'updated_at', type: 'datetime')]
        public ?\DateTimeInterface $updated_at = null
    ) {
    }
}
