<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'Invoice',
    operations: [
        new GetCollection(),
    ],
    normalizationContext: ['groups' => ['invoice:read']],
    filters: [DevExtremeFilter::class],
)]
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'comprobante')]
class Invoice
{
    public function __construct(
        #[Groups(['invoice:read'])]
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'comprobante_id', type: 'string')]
        public ?string $comprobante_id = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'movimiento_id', type: 'string')]
        public ?string $movimiento_id = null,
        #[ORM\Column(name: 'raw_document_id', type: 'integer')]
        public ?int $raw_document_id = null,
        #[Groups(['invoice:read'])]
        #[ORM\ManyToOne(targetEntity: Customer::class)]
        #[ORM\JoinColumn(name: 'cliente_id', referencedColumnName: 'ruc')]
        public ?Customer $cliente = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'tipo_comprobante_id', type: 'string')]
        public ?string $tipo_comprobante_id = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'fecha', type: 'datetime')]
        public ?\DateTimeInterface $fecha = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'serie', type: 'string')]
        public ?string $serie = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'numero', type: 'string')]
        public ?string $numero = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'inafecto', type: 'boolean')]
        public ?bool $inafecto = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'subtotal', type: 'float')]
        public ?float $subtotal = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'gravada', type: 'float')]
        public ?float $gravada = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'igv', type: 'float')]
        public ?float $igv = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'rc', type: 'float')]
        public ?float $rc = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'total', type: 'float')]
        public ?float $total = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'discount_percent', type: 'integer')]
        public ?int $discount_percent = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'discount_amount', type: 'float')]
        public ?float $discount_amount = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'voided', type: 'integer')]
        public ?int $voided = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'digest', type: 'string')]
        public ?string $digest = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'ticket_baja', type: 'string')]
        public ?string $ticket_baja = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'correlativo_baja', type: 'string')]
        public ?string $correlativo_baja = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'envio_sunat', type: 'string')]
        public ?string $envio_sunat = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'mensaje_sunat', type: 'text')]
        public ?string $mensaje_sunat = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'created_at', type: 'datetime')]
        public ?\DateTimeInterface $created_at = null,
        #[Groups(['invoice:read'])]
        #[ORM\Column(name: 'updated_at', type: 'datetime')]
        public ?\DateTimeInterface $updated_at = null,
    ) {
    }
}
