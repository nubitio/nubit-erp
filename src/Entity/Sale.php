<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Core\Infrastructure\Doctrine\Filter\DevExtremeFilter;
use App\State\Sale\SaleProcessor;
use App\State\Sale\SaleProvider;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'movimiento')]
#[ApiResource(
    shortName: 'Movement',
    normalizationContext: ['groups' => ['sale:read', 'inbound:read', 'outbound:read']],
    denormalizationContext: ['groups' => ['sale:write']],
    filters: [DevExtremeFilter::class],
    provider: SaleProvider::class,
    processor: SaleProcessor::class
)]
class Sale
{
    public function __construct(
        #[Groups(['sale:read', 'inbound:read', 'outbound:read'])]
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'CUSTOM')]
        #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
        #[ORM\Column(name: 'movimiento_id', type: 'string')]
        public ?string $id = null,
        #[Groups([
            'sale:read',
            'sale:write',
            'inbound:read',
            'outbound:read',
        ])]
        #[ORM\ManyToOne(targetEntity: LogisticOperation::class)]
        #[ORM\JoinColumn(name: 'operacion_id', referencedColumnName: 'operacion_id')]
        public ?LogisticOperation $operacion = null,
        #[Groups([
            'sale:read',
            'sale:write',
            'inbound:read',
            'outbound:read',
        ])]
        #[ORM\ManyToOne(targetEntity: Warehouse::class)]
        #[ORM\JoinColumn(name: 'almacen_id', referencedColumnName: 'almacen_id')]
        public ?Warehouse $almacen = null,
        #[Groups(['sale:read', 'sale:write'])]
        #[ORM\ManyToOne(targetEntity: Customer::class)]
        #[ORM\JoinColumn(name: 'cliente_id', referencedColumnName: 'ruc')]
        public ?Customer $cliente = null,
        #[Groups(['sale:read', 'sale:write'])]
        #[ORM\ManyToOne(targetEntity: PriceList::class)]
        #[ORM\JoinColumn(name: 'price_list_id', referencedColumnName: 'id')]
        public ?PriceList $price_list = null,
        #[Groups([
            'sale:read',
            'sale:write',
            'inbound:read',
            'outbound:read',
        ])]
        #[ORM\Column(name: 'tipo_id', type: 'integer')]
        public ?int $tipo_id = null,
        #[Groups([
            'sale:read',
            'sale:write',
            'inbound:read',
            'outbound:read',
        ])]
        #[ORM\Column(name: 'fecha', type: 'date')]
        public ?DateTimeInterface $fecha = null,
        #[Groups([
            'sale:write',
            'inbound:read',
            'outbound:read',
        ])]
        #[ORM\ManyToOne(targetEntity: Supplier::class)]
        #[ORM\JoinColumn(name: 'proveedor_id', referencedColumnName: 'proveedor_id')]
        public ?Supplier $proveedor = null,
        #[Groups(['sale:read', 'inbound:read', 'outbound:read'])]
        #[ORM\Column(name: 'forma_pago', type: 'integer')]
        public ?int $forma_pago = null,
        #[Groups([
            'sale:read',
            'sale:write',
            'inbound:read',
            'outbound:read',
        ])]
        #[ORM\ManyToOne(targetEntity: PaymentMethod::class)]
        #[ORM\JoinColumn(name: 'tipo_pago', referencedColumnName: 'id')]
        public ?PaymentMethod $tipo_pago = null,
        #[Groups(['sale:read'])]
        #[ORM\Column(name: 'tipo_tarjeta', type: 'integer')]
        public ?int $tipo_tarjeta = null,
        #[Groups(['sale:read', 'sale:write'])]
        #[ORM\Column(name: 'serie', type: 'string')]
        public ?string $serie = null,
        #[Groups(['sale:read', 'sale:write'])]
        #[ORM\Column(name: 'numero', type: 'string')]
        public ?string $numero = null,
        #[Groups(['sale:read', 'sale:write'])]
        #[ORM\Column(name: 'descuento')]
        public ?float $descuento = null,
        #[Groups([
            'sale:read',
            'sale:write',
            'inbound:read',
            'outbound:read',
        ])]
        #[ORM\Column(name: 'glosa', type: 'string')]
        public ?string $glosa = null,
        #[Groups(['sale:read', 'sale:write'])]
        #[ORM\Column(name: 'orden_id', type: 'integer')]
        public ?int $orden_id = null,
        #[Groups(['sale:read', 'sale:write'])]
        #[ORM\Column(name: 'has_invoice', type: 'boolean')]
        public ?bool $has_invoice = null,
        #[Groups(['sale:read', 'sale:write'])]
        #[ORM\Column(name: 'is_consume', type: 'boolean')]
        public ?bool $is_consume = null,
        #[Groups(['sale:read', 'sale:write'])]
        #[ORM\Column(name: 'caja_id', type: 'string')]
        public ?string $caja_id = null,
        #[Groups([
            'sale:read',
            'sale:write',
            'inbound:read',
            'outbound:read',
        ])]
        #[ORM\Column(name: 'estado', type: 'integer')]
        public ?int $estado = null,
        #[Groups(['sale:read', 'inbound:read', 'outbound:read'])]
        #[ORM\Column(name: 'date_created', type: 'datetime')]
        public ?DateTimeInterface $date_created = null,
        #[Groups(['sale:read', 'inbound:read', 'outbound:read'])]
        #[ORM\Column(name: 'date_updated', type: 'datetime')]
        public ?DateTimeInterface $date_updated = null,
        #[Groups(['sale:read', 'inbound:read', 'outbound:read'])]
        #[ORM\Column(name: 'created_by', type: 'integer')]
        public ?int $created_by = null,
        #[Groups(['sale:read', 'inbound:read', 'outbound:read'])]
        #[ORM\Column(name: 'updated_by', type: 'integer')]
        public ?int $updated_by = null,
        #[Groups(['sale:read', 'sale:write'])]
        #[ORM\Column(name: 'igv', type: 'integer')]
        public ?int $igv = null,
        #[Groups(['sale:read', 'sale:write'])]
        #[ORM\Column(name: 'recargo_consumo', type: 'integer')]
        public ?int $recargo_consumo = null,
        #[Groups(['sale:read', 'sale:write'])]
        #[ORM\Column(name: 'invoice', type: 'string')]
        public ?string $invoice = null,
        #[Groups([
            'sale:read',
            'sale:write',
            'inbound:read',
            'outbound:read',
        ])]
        #[ORM\Column(name: 'subtotal')]
        public ?float $subtotal = null,
        #[Groups([
            'sale:read',
            'sale:write',
            'inbound:read',
            'outbound:read',
        ])]
        #[ORM\Column(name: 'total')]
        public ?float $total = null,
        #[Groups([
            'sale:read',
            'sale:write',
            'inbound:read',
            'outbound:read',
        ])]
        /**
         * @var SaleItem[] $items
         *
         * @phpstan-var ArrayCollection<int , SaleItem>|array<int , SaleItem>
         */
        #[ORM\OneToMany(mappedBy: 'movimiento', targetEntity: SaleItem::class, cascade: [
            'persist',
            'remove',
        ], orphanRemoval: true)]
        public Collection | array $items = new ArrayCollection(),
    ) {
    }

    public function addItem(SaleItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->movimiento = $this;
        }

        return $this;
    }

    public function removeItem(SaleItem $item): static
    {
        if ($this->items->removeElement($item)) {
            // set the owning side to null (unless already changed)
            if ($item->movimiento === $this) {
                $item->movimiento = null;
            }
        }

        return $this;
    }
}
