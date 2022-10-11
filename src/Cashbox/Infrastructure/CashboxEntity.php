<?php

namespace App\Cashbox\Infrastructure;

use App\Cashbox\Domain\Cashbox;
use App\CashboxDetail\Infrastructure\CashboxDetailEntity;
use App\Domain\Annotation\IgnoreAutowire;
use App\Local\Infrastructure\LocalEntity;
use App\User\Infrastructure\UserEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "caja")]
class CashboxEntity
{
    #[Id]
    #[Column(name: "caja_id", type: "string")]
    public ?string $caja_id;
    #[Column(name: "local_id", type: "string")]
    public ?string $local_id;
    #[Column(name: "apertura", type: "datetime")]
    public ?\DateTime $apertura;
    #[Column(name: "cierre", type: "datetime")]
    public ?\DateTime $cierre;
    #[Column(name: "usuario_id", type: "integer")]
    public ?int $usuario_id;
    #[Column(name: "estado", type: "integer")]
    public ?int $estado;
    #[ManyToOne(targetEntity: LocalEntity::class)]
    #[JoinColumn(name: "local_id", referencedColumnName: "local_id")]
    public LocalEntity $local;
    #[ManyToOne(targetEntity: UserEntity::class)]
    #[JoinColumn(name: "usuario_id", referencedColumnName: "usuario_id")]
    public UserEntity $user;
    #[OneToMany(mappedBy: "cashbox", targetEntity: CashboxDetailEntity::class)]
    /**
     * @var CashboxDetailEntity[] $items
     */
    public $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function mapToDomainModel(): Cashbox
    {
        return new Cashbox(
            $this->caja_id,
            $this->local_id,
            $this->apertura,
            $this->cierre,
            $this->usuario_id,
            $this->estado,
            $this->local->mapToDomainModel(),
            $this->user->mapToDomainModel(),
            $this->items->map(fn(CashboxDetailEntity $item) => $item->mapToDomainModel())->toArray(),
        );
    }

    public function mapFromDomainModel(Cashbox $cashbox): CashboxEntity
    {
        $this->caja_id = $cashbox->caja_id ?? $this->caja_id;
        $this->local_id = $cashbox->local_id ?? $this->local_id;
        $this->apertura = $cashbox->apertura ?? $this->apertura;
        $this->cierre = $cashbox->cierre ?? $this->cierre ?? null;
        $this->usuario_id = $cashbox->usuario_id ?? $this->usuario_id;
        $this->estado = $cashbox->estado ?? $this->estado;

        return $this;
    }
}