<?php

namespace App\CashboxNew\Infrastructure;

use App\Branch\Infrastructure\BranchEntity;
use App\CashboxNewDetail\Infrastructure\CashboxDetailEntity;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\User\Infrastructure\UserEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'caja')]
class CashboxEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'caja_id', type: 'string')]
        public ?string $caja_id = null,
        #[ORM\ManyToOne(targetEntity: BranchEntity::class)]
        #[ORM\JoinColumn(name: 'local_id', referencedColumnName: 'local_id')]
        public ?BranchEntity $local = null,
        #[ORM\Column(name: 'apertura', type: 'datetime')]
        public ?\DateTimeInterface $apertura = null,
        #[ORM\Column(name: 'cierre', type: 'datetime')]
        public ?\DateTimeInterface $cierre = null,
        #[ORM\ManyToOne(targetEntity: UserEntity::class)]
        #[ORM\JoinColumn(name: 'usuario_id', referencedColumnName: 'usuario_id')]
        public ?UserEntity $usuario = null,
        #[ORM\Column(name: 'estado', type: 'integer')]
        public ?int $estado = null,
        /**
         * @var CashboxDetailEntity[] $details
         *
         * @phpstan-var ArrayCollection<int , CashboxDetailEntity>|array<int , CashboxDetailEntity>
         */
        #[ORM\OneToMany(mappedBy: 'cashbox', targetEntity: CashboxDetailEntity::class, cascade: ['persist', 'remove'])]
        public Collection|array $details = new ArrayCollection(),
    ) {
    }
}
