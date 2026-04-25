<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Core\Http\ApiResponse;
use App\Efact\Doctrine\Filter\DevExtremeFilter;
use App\Dto\CashRegister\CashRegisterCloseDto;
use App\Dto\CashRegister\CashRegisterOpenDto;
use App\Repository\CashRegisterRepository;
use App\State\CashRegister\CashRegisterCloseProcessor;
use App\State\CashRegister\CashRegisterDashboardProvider;
use App\State\CashRegister\CashRegisterOpenProcessor;
use App\State\CashRegister\CashRegisterShiftsProvider;
use App\Entity\CashSession;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CashRegisterRepository::class)]
#[ApiResource(
    shortName: 'CashRegister',
    operations: [
        new GetCollection(
            uriTemplate: '/cash-registers/dashboard',
            paginationEnabled: false,
            normalizationContext: ['groups' => ['cash_register_dashboard:read']],
            provider: CashRegisterDashboardProvider::class,
        ),
        new Post(
            uriTemplate: '/cash-registers/{id}/open',
            normalizationContext: ['groups' => ['cash_session:read']],
            input: CashRegisterOpenDto::class,
            output: CashSession::class,
            processor: CashRegisterOpenProcessor::class,
        ),
        new Post(
            uriTemplate: '/cash-registers/{id}/close',
            paginationEnabled: false,
            input: CashRegisterCloseDto::class,
            output: ApiResponse::class,
            processor: CashRegisterCloseProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/cash-registers/{id}/shifts',
            uriVariables: [
                'id' => new Link(
                    fromProperty: 'id',
                    fromClass: CashRegister::class,
                ),
            ],
            paginationEnabled: true,
            normalizationContext: ['groups' => ['cash_session:read']],
            output: CashSession::class,
            provider: CashRegisterShiftsProvider::class,
        ),
        new GetCollection(uriTemplate: '/cash-registers'),
        new Post(uriTemplate: '/cash-registers'),
        new Get(uriTemplate: '/cash-registers/{id}'),
        new Patch(uriTemplate: '/cash-registers/{id}'),
        new Delete(uriTemplate: '/cash-registers/{id}'),
    ],
    normalizationContext: ['groups' => ['cash_register:read']],
    filters: [DevExtremeFilter::class],
    mercure: true,
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
    #[ApiProperty(description: 'cash_register.branch.label')]
    private Branch $branch;

    #[Groups(['cash_register:read'])]
    #[ORM\Column(length: 255)]
    #[ApiProperty(description: 'cash_register.name.label')]
    private string $name;

    /**
     * All cash sessions (shifts) that belong to this register.
     *
     * Previously, the absence of this collection forced callers to use a custom
     * API Platform provider (CashRegisterShiftsProvider) for the
     * GET /cash-registers/{id}/shifts endpoint. The provider may now delegate
     * to this collection via Doctrine navigation, or continue to use raw queries
     * for pagination — both are supported.
     *
     * @var Collection<int, CashSession>
     */
    #[ORM\OneToMany(
        targetEntity: CashSession::class,
        mappedBy: 'cashRegister',
        cascade: [],
        fetch: 'EXTRA_LAZY',
        orphanRemoval: false,
    )]
    #[ORM\OrderBy(['openedAt' => 'DESC'])]
    private Collection $cashSessions;

    public function __construct()
    {
        $this->cashSessions = new ArrayCollection();
    }

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

    /**
     * @return Collection<int, CashSession>
     */
    public function getCashSessions(): Collection
    {
        return $this->cashSessions;
    }

    /**
     * Returns true if this register was auto-created by the enterprise-guardrails
     * migration (Version20260227180000) rather than by a real user.
     *
     * Synthetic registers carry the sentinel name {@see CashRegisterRepository::MIGRATION_AUTO_NAME}.
     * Audit logs, reports, and POS UIs should exclude them.
     */
    public function isMigrationAuto(): bool
    {
        return $this->name === CashRegisterRepository::MIGRATION_AUTO_NAME;
    }
}
