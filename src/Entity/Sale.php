<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Core\Util\ReceiptPayload;
use App\Repository\SaleRepository;
use App\State\Sale\SaleProcessor;
use App\State\Sale\SaleProvider;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SaleRepository::class)]
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
    #[Groups(['sale:read', 'inbound:read', 'outbound:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'movimiento_id', type: 'string')]
    private ?string $id = null;

    #[Groups([
        'sale:read',
        'sale:write',
        'inbound:read',
        'outbound:read',
    ])]
    #[ORM\ManyToOne(targetEntity: LogisticOperation::class)]
    #[ORM\JoinColumn(name: 'operacion_id', referencedColumnName: 'operacion_id')]
    private ?LogisticOperation $operacion = null;

    #[Groups([
        'sale:read',
        'sale:write',
        'inbound:read',
        'outbound:read',
    ])]
    #[Assert\NotBlank]
    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(name: 'almacen_id', referencedColumnName: 'almacen_id')]
    private ?Warehouse $almacen = null;

    #[Groups(['sale:read'])]
    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: true)]
    private ?Branch $branch = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'cliente_id', referencedColumnName: 'ruc')]
    private ?Customer $cliente = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\ManyToOne(targetEntity: PriceList::class)]
    #[ORM\JoinColumn(name: 'price_list_id', referencedColumnName: 'id')]
    private ?PriceList $price_list = null;

    #[Groups([
        'sale:read',
        'sale:write',
        'inbound:read',
        'outbound:read',
    ])]
    #[ORM\Column(name: 'tipo_id', type: 'integer')]
    private int $tipo_id;

    #[Assert\NotBlank]
    #[Groups([
        'sale:read',
        'sale:write',
        'inbound:read',
        'outbound:read',
    ])]
    #[ORM\Column(name: 'fecha', type: 'datetime_immutable')]
    private DateTimeImmutable $fecha;

    #[Groups([
        'sale:write',
        'inbound:read',
        'outbound:read',
    ])]
    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(name: 'proveedor_id', referencedColumnName: 'proveedor_id')]
    private ?Supplier $proveedor = null;

    #[Groups(['sale:read', 'inbound:read', 'outbound:read'])]
    #[ORM\Column(name: 'forma_pago', type: 'integer', nullable: true)]
    private ?int $forma_pago = null;

    #[Groups([
        'sale:read',
        'sale:write',
        'inbound:read',
        'outbound:read',
    ])]
    #[ORM\ManyToOne(targetEntity: PaymentMethod::class)]
    #[ORM\JoinColumn(name: 'tipo_pago', referencedColumnName: 'id')]
    private ?PaymentMethod $tipo_pago = null;

    #[Groups(['sale:read'])]
    #[ORM\Column(name: 'tipo_tarjeta', type: 'integer', nullable: true)]
    private ?int $tipo_tarjeta = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'serie', type: 'string', nullable: true)]
    private ?string $serie = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'numero', type: 'string', nullable: true)]
    private ?string $numero = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'descuento', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $descuento = null;

    #[Groups([
        'sale:read',
        'sale:write',
        'inbound:read',
        'outbound:read',
    ])]
    #[ORM\Column(name: 'glosa', type: 'string', nullable: true)]
    private ?string $glosa = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'orden_id', type: 'integer', nullable: true)]
    private ?int $orden_id = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'has_invoice', type: 'boolean', options: ['default' => false])]
    private bool $has_invoice = false;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'is_consume', type: 'boolean', options: ['default' => false])]
    private bool $is_consume = false;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'caja_id', type: 'integer', nullable: true)]
    private ?int $caja_id = null;

    #[Groups([
        'sale:read',
        'sale:write',
        'inbound:read',
        'outbound:read',
    ])]
    #[ORM\Column(name: 'estado', type: 'integer', options: ['default' => 1])]
    private int $estado = 1;

    #[Groups(['sale:read', 'inbound:read', 'outbound:read'])]
    #[ORM\Column(name: 'date_created', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $date_created;

    #[Groups(['sale:read', 'inbound:read', 'outbound:read'])]
    #[ORM\Column(name: 'date_updated', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $date_updated = null;

    #[Groups(['sale:read', 'inbound:read', 'outbound:read'])]
    #[ORM\Column(name: 'created_by', type: 'integer')]
    private int $created_by;

    #[Groups(['sale:read', 'inbound:read', 'outbound:read'])]
    #[ORM\Column(name: 'updated_by', type: 'integer', nullable: true)]
    private ?int $updated_by = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'igv', type: 'integer', nullable: true)]
    private ?int $igv = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'recargo_consumo', type: 'integer', nullable: true)]
    private ?int $recargo_consumo = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'tipo_operacion', type: 'string', length: 4, nullable: true)]
    private ?string $tipo_operacion = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'detraccion_codigo', type: 'string', length: 10, nullable: true)]
    private ?string $detraccion_codigo = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'detraccion_porcentaje', type: 'float', nullable: true)]
    private ?float $detraccion_porcentaje = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'detraccion_monto', type: 'float', nullable: true)]
    private ?float $detraccion_monto = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'detraccion_medio_pago', type: 'string', length: 10, nullable: true)]
    private ?string $detraccion_medio_pago = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'detraccion_valor_referencial', type: 'float', nullable: true)]
    private ?float $detraccion_valor_referencial = null;

    #[Groups(['sale:read', 'sale:write'])]
    #[ORM\Column(name: 'invoice', type: 'string', nullable: true)]
    private ?string $invoice = null;

    #[ORM\OneToOne(targetEntity: TaxDocument::class, mappedBy: 'sale')]
    private ?TaxDocument $taxDocument = null;

    #[Groups([
        'sale:read',
        'sale:write',
        'inbound:read',
        'outbound:read',
    ])]
    #[ORM\Column(name: 'subtotal', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $subtotal = '0.00';

    #[Groups([
        'sale:read',
        'sale:write',
        'inbound:read',
        'outbound:read',
    ])]
    #[ORM\Column(name: 'total', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $total = '0.00';

    /**
     * @var Collection<int, SaleItem>&iterable<SaleItem> $items
     */
    #[Groups([
        'sale:read',
        'sale:write',
        'inbound:read',
        'outbound:read',
    ])]
    #[ORM\OneToMany(targetEntity: SaleItem::class, mappedBy: 'movimiento', cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function addItem(SaleItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setMovimiento($this);
        }

        return $this;
    }

    public function removeItem(SaleItem $item): static
    {
        // set the owning side to null (unless already changed)
        if ($this->items->removeElement($item) && $item->getMovimiento() === $this) {
            $item->setMovimiento(null);
        }

        return $this;
    }

    public function getTaxDocument(): ?TaxDocument
    {
        return $this->taxDocument;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getOperacion(): ?LogisticOperation
    {
        return $this->operacion;
    }

    public function setOperacion(?LogisticOperation $operacion): static
    {
        $this->operacion = $operacion;

        return $this;
    }

    public function getAlmacen(): ?Warehouse
    {
        return $this->almacen;
    }

    public function setAlmacen(?Warehouse $almacen): static
    {
        $this->almacen = $almacen;

        return $this;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    public function getCliente(): ?Customer
    {
        return $this->cliente;
    }

    public function setCliente(?Customer $cliente): static
    {
        $this->cliente = $cliente;

        return $this;
    }

    public function getPriceList(): ?PriceList
    {
        return $this->price_list;
    }

    public function setPriceList(?PriceList $price_list): static
    {
        $this->price_list = $price_list;

        return $this;
    }

    public function getTipoId(): ?int
    {
        return $this->tipo_id;
    }

    public function setTipoId(?int $tipo_id): static
    {
        $this->tipo_id = $tipo_id;

        return $this;
    }

    public function getFecha(): ?DateTimeImmutable
    {
        return $this->fecha;
    }

    public function setFecha(?DateTimeInterface $fecha): static
    {
        $this->fecha = $fecha instanceof DateTimeImmutable
            ? $fecha
            : ($fecha
                ? DateTimeImmutable::createFromInterface($fecha)
                : null);

        return $this;
    }

    public function getProveedor(): ?Supplier
    {
        return $this->proveedor;
    }

    public function setProveedor(?Supplier $proveedor): static
    {
        $this->proveedor = $proveedor;

        return $this;
    }

    public function getFormaPago(): ?int
    {
        return $this->forma_pago;
    }

    public function setFormaPago(?int $forma_pago): static
    {
        $this->forma_pago = $forma_pago;

        return $this;
    }

    public function getTipoPago(): ?PaymentMethod
    {
        return $this->tipo_pago;
    }

    public function setTipoPago(?PaymentMethod $tipo_pago): static
    {
        $this->tipo_pago = $tipo_pago;

        return $this;
    }

    public function getTipoTarjeta(): ?int
    {
        return $this->tipo_tarjeta;
    }

    public function setTipoTarjeta(?int $tipo_tarjeta): static
    {
        $this->tipo_tarjeta = $tipo_tarjeta;

        return $this;
    }

    public function getSerie(): ?string
    {
        return $this->serie;
    }

    public function setSerie(?string $serie): static
    {
        $this->serie = $serie;

        return $this;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getDescuento(): ?string
    {
        return $this->descuento;
    }

    public function setDescuento(?string $descuento): static
    {
        $this->descuento = $descuento;

        return $this;
    }

    public function getGlosa(): ?string
    {
        return $this->glosa;
    }

    public function setGlosa(?string $glosa): static
    {
        $this->glosa = $glosa;

        return $this;
    }

    public function getOrdenId(): ?int
    {
        return $this->orden_id;
    }

    public function setOrdenId(?int $orden_id): static
    {
        $this->orden_id = $orden_id;

        return $this;
    }

    public function isHasInvoice(): ?bool
    {
        return $this->has_invoice;
    }

    public function setHasInvoice(?bool $has_invoice): static
    {
        $this->has_invoice = $has_invoice;

        return $this;
    }

    public function isIsConsume(): ?bool
    {
        return $this->is_consume;
    }

    public function setIsConsume(?bool $is_consume): static
    {
        $this->is_consume = $is_consume;

        return $this;
    }

    public function getCajaId(): ?int
    {
        return $this->caja_id;
    }

    public function setCajaId(?int $caja_id): static
    {
        $this->caja_id = $caja_id;

        return $this;
    }

    public function getEstado(): ?int
    {
        return $this->estado;
    }

    public function setEstado(?int $estado): static
    {
        $this->estado = $estado;

        return $this;
    }

    public function getDateCreated(): ?DateTimeImmutable
    {
        return $this->date_created;
    }

    public function setDateCreated(?DateTimeInterface $date_created): static
    {
        $this->date_created = $date_created instanceof DateTimeImmutable
            ? $date_created
            : ($date_created
                ? DateTimeImmutable::createFromInterface($date_created)
                : null);

        return $this;
    }

    public function getDateUpdated(): ?DateTimeImmutable
    {
        return $this->date_updated;
    }

    public function setDateUpdated(?DateTimeInterface $date_updated): static
    {
        $this->date_updated = $date_updated instanceof DateTimeImmutable
            ? $date_updated
            : ($date_updated
                ? DateTimeImmutable::createFromInterface($date_updated)
                : null);

        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->created_by;
    }

    public function setCreatedBy(?int $created_by): static
    {
        $this->created_by = $created_by;

        return $this;
    }

    public function getUpdatedBy(): ?int
    {
        return $this->updated_by;
    }

    public function setUpdatedBy(?int $updated_by): static
    {
        $this->updated_by = $updated_by;

        return $this;
    }

    public function getIgv(): ?int
    {
        return $this->igv;
    }

    public function setIgv(?int $igv): static
    {
        $this->igv = $igv;

        return $this;
    }

    public function getRecargoConsumo(): ?int
    {
        return $this->recargo_consumo;
    }

    public function setRecargoConsumo(?int $recargo_consumo): static
    {
        $this->recargo_consumo = $recargo_consumo;

        return $this;
    }

    public function getTipoOperacion(): ?string
    {
        return $this->tipo_operacion;
    }

    public function setTipoOperacion(?string $tipo_operacion): static
    {
        $this->tipo_operacion = $tipo_operacion;

        return $this;
    }

    public function getDetraccionCodigo(): ?string
    {
        return $this->detraccion_codigo;
    }

    public function setDetraccionCodigo(?string $detraccion_codigo): static
    {
        $this->detraccion_codigo = $detraccion_codigo;

        return $this;
    }

    public function getDetraccionPorcentaje(): ?float
    {
        return $this->detraccion_porcentaje;
    }

    public function setDetraccionPorcentaje(?float $detraccion_porcentaje): static
    {
        $this->detraccion_porcentaje = $detraccion_porcentaje;

        return $this;
    }

    public function getDetraccionMonto(): ?float
    {
        return $this->detraccion_monto;
    }

    public function setDetraccionMonto(?float $detraccion_monto): static
    {
        $this->detraccion_monto = $detraccion_monto;

        return $this;
    }

    public function getDetraccionMedioPago(): ?string
    {
        return $this->detraccion_medio_pago;
    }

    public function setDetraccionMedioPago(?string $detraccion_medio_pago): static
    {
        $this->detraccion_medio_pago = $detraccion_medio_pago;

        return $this;
    }

    public function getDetraccionValorReferencial(): ?float
    {
        return $this->detraccion_valor_referencial;
    }

    public function setDetraccionValorReferencial(?float $detraccion_valor_referencial): static
    {
        $this->detraccion_valor_referencial = $detraccion_valor_referencial;

        return $this;
    }

    public function getInvoice(): ?string
    {
        return $this->invoice;
    }

    public function setInvoice(?string $invoice): static
    {
        $this->invoice = $invoice;

        return $this;
    }

    #[Groups(['sale:read', 'inbound:read', 'outbound:read'])]
    public function getInvoiceSunatStatus(): ?string
    {
        return $this->taxDocument?->getSunatStatus();
    }

    #[Groups(['sale:read', 'inbound:read', 'outbound:read'])]
    public function getInvoiceVoided(): ?int
    {
        return $this->taxDocument?->getVoidTicket() != null ? 2 : 1;
    }

    #[Groups(['sale:read'])]
    public function getPublicReceiptUrl(): ?string
    {
        if (!$this->taxDocument) {
            return null;
        }

        return ReceiptPayload::publicUrl(
            $this->taxDocument->getDocumentType(),
            $this->taxDocument->getId(),
        );
    }

    public function getSubtotal(): ?string
    {
        return $this->subtotal;
    }

    public function setSubtotal(?string $subtotal): static
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(?string $total): static
    {
        $this->total = $total;

        return $this;
    }

    /**
     * @return Collection<int, SaleItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }
}
