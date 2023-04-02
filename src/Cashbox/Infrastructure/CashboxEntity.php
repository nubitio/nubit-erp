<?php

namespace App\Cashbox\Infrastructure;

use App\Branch\Infrastructure\BranchEntity;
use App\CashboxDetail\Infrastructure\CashboxDetailEntity;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\User\Infrastructure\UserEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'caja')]
class CashboxEntity
{
    #[Id]
    #[GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: UuidGenerator::class)]
    #[Column(name: 'caja_id', type: 'string')]
    public ?string $caja_id;
    #[Column(name: 'local_id', type: 'string')]
    public ?string $local_id;
    #[Column(name: 'apertura', type: 'datetime')]
    public ?\DateTime $apertura;
    #[Column(name: 'cierre', type: 'datetime')]
    public ?\DateTime $cierre;
    #[Column(name: 'usuario_id', type: 'integer')]
    public ?int $usuario_id;
    #[Column(name: 'estado', type: 'integer')]
    public ?int $estado;
    #[ManyToOne(targetEntity: BranchEntity::class)]
    #[JoinColumn(name: 'local_id', referencedColumnName: 'local_id')]
    public BranchEntity $local;
    #[ManyToOne(targetEntity: UserEntity::class)]
    #[JoinColumn(name: 'usuario_id', referencedColumnName: 'usuario_id')]
    public UserEntity $user;
    /** @var ArrayCollection<int , CashboxDetailEntity>|array<int, CashboxDetailEntity> */
    #[OneToMany(mappedBy: 'cashbox', targetEntity: CashboxDetailEntity::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public array|Collection $details;

    public function __construct()
    {
        $this->details = new ArrayCollection();
    }
}
