<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Core\Http\ApiResponse;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Dto\CashRegister\CashRegisterCloseDto;
use App\Dto\CashRegister\CashRegisterOpenDto;
use App\Repository\CashRegisterRepository;
use App\State\CashRegister\CashRegisterCloseProcessor;
use App\State\CashRegister\CashRegisterDashboardProvider;
use App\State\CashRegister\CashRegisterOpenProcessor;
use App\State\CashRegister\CashRegisterShiftsProvider;
use App\Entity\CashSession;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CashRegisterRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/cash-registers/dashboard',
            provider: CashRegisterDashboardProvider::class,
        ),
    ],
    paginationEnabled: false
)]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/cash-registers/{id}/open',
            input: CashRegisterOpenDto::class,
            output: CashShift::class,
            processor: CashRegisterOpenProcessor::class,
        ),
    ],
)]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/cash-registers/{id}/close',
            input: CashRegisterCloseDto::class,
            output: ApiResponse::class,
            processor: CashRegisterCloseProcessor::class,
        ),
    ],
    paginationEnabled: false,
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/cash-registers/{id}/shifts',
            uriVariables: [
                'id' => new Link(
                    fromProperty: 'id',
                    fromClass: CashRegister::class,
                ),
            ],
            normalizationContext: ['groups' => ['cash_session:read']],
            output: CashSession::class,
            provider: CashRegisterShiftsProvider::class,
        ),
    ],
    paginationEnabled: true,
)]
#[ApiResource(
    normalizationContext: ['groups' => ['cash_register:read']],
    filters: [DevExtremeFilter::class],
)]
class CashRegister
{
    #[Groups(['cash_register:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['cash_register:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: false)]
    private Branch $branch;

    #[Groups(['cash_register:read'])]
    #[ORM\Column(length: 255)]
    private string $name;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBranch(): Branch
    {
        return $this->branch;
    }

    public function setBranch(Branch $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
