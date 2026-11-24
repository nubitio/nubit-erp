<?php

declare(strict_types=1);

namespace App\CashRegister\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\CashRegister\Dto\CashRegisterCloseDto;
use App\CashRegister\Dto\CashRegisterCloseResponseDto;
use App\CashRegister\Dto\CashRegisterOpenDto;
use App\CashRegister\Dto\CashRegisterSummaryDto;
use App\CashRegister\Entity\CashSession;
use App\CashRegister\Repository\CashRegisterRepository;
use App\CashRegister\State\CashRegisterCloseProcessor;
use App\CashRegister\State\CashRegisterDashboardProvider;
use App\CashRegister\State\CashRegisterOpenProcessor;
use App\CashRegister\State\CashRegistersByBranchProvider;
use App\CashRegister\State\CashRegisterShiftsProvider;
use App\CashRegister\State\CashRegisterSummaryProvider;
use App\Efact\Domain\ActivatableInterface;
use App\Organization\Entity\Branch;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
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
            security: "is_granted('perm:cash.view')",
        ),
        new Get(
            uriTemplate: '/cash-registers/summary',
            normalizationContext: ['groups' => ['cash_register_summary:read']],
            output: CashRegisterSummaryDto::class,
            provider: CashRegisterSummaryProvider::class,
            security: "is_granted('perm:cash.view')",
        ),
        new Post(
            uriTemplate: '/cash-registers/{id}/open',
            normalizationContext: ['groups' => ['cash_session:read']],
            input: CashRegisterOpenDto::class,
            output: CashSession::class,
            processor: CashRegisterOpenProcessor::class,
            security: "is_granted('perm:cash.open')",
        ),
        new Post(
            uriTemplate: '/cash-registers/{id}/close',
            paginationEnabled: false,
            status: 200,
            input: CashRegisterCloseDto::class,
            output: CashRegisterCloseResponseDto::class,
            normalizationContext: ['groups' => ['cash_register_close:read']],
            processor: CashRegisterCloseProcessor::class,
            security: "is_granted('perm:cash.close')",
        ),
        new GetCollection(
            uriTemplate: '/cash-registers/{id}/shifts',
            uriVariables: [
                'id' => new Link(fromProperty: 'id', fromClass: CashRegister::class),
            ],
            paginationEnabled: true,
            normalizationContext: ['groups' => ['cash_session:read']],
            output: CashSession::class,
            provider: CashRegisterShiftsProvider::class,
            security: "is_granted('perm:cash.view')",
        ),
        new GetCollection(
            uriTemplate: '/cash-registers',
            provider: CashRegistersByBranchProvider::class,
            security: "is_granted('perm:cash.view')",
        ),
        new Post(uriTemplate: '/cash-registers', security: "is_granted('perm:cash.manage')"),
        new Get(uriTemplate: '/cash-registers/{id}', security: "is_granted('perm:cash.view')"),
        new Patch(uriTemplate: '/cash-registers/{id}', security: "is_granted('perm:cash.manage')"),
        new Delete(uriTemplate: '/cash-registers/{id}', security: "is_granted('perm:cash.manage')"),
    ],
    normalizationContext: ['groups' => ['cash_register:read']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class CashRegister implements ActivatableInterface
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

    #[Groups(['cash_register:read'])]
    #[ApiProperty(description: 'cash_register.is_active.label')]
    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

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

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    /**
     * Whether this register currently has an open shift.
     * Exposed as `is_open` in the `cash_register:read` serialization group
     * so that POS clients can determine status without a separate request.
     */
    #[Groups(['cash_register:read'])]
    public function isOpen(): bool
    {
        foreach ($this->cashSessions as $session) {
            if ($session->getStatus() === 'OPEN') {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the currently open CashSession, or null if the register is closed.
     * Exposed as `current_shift` in the `cash_register:read` serialization group.
     *
     * Returns a plain array to avoid circular reference issues
     * (CashSession → CashRegister → CashSession).
     *
     * @return array<string, mixed>|null
     */
    #[Groups(['cash_register:read'])]
    public function getCurrentShift(): ?array
    {
        foreach ($this->cashSessions as $session) {
            if ($session->getStatus() === 'OPEN') {
                return [
                    'id' => $session->getId(),
                    'opening_amount' => (float) $session->getOpeningAmount(),
                    'closing_amount' => $session->getCountedAmount() !== null
                        ? (float) $session->getCountedAmount()
                        : null,
                    'opening_date' => $session->getOpenedAt()->format(\DateTimeInterface::ATOM),
                    'closing_date' => $session->getClosedAt()?->format(\DateTimeInterface::ATOM),
                    'opened_by' => [
                        'id' => $session->getOpenedBy()->getId(),
                        'name' => $session->getOpenedBy()->getUsername(),
                    ],
                ];
            }
        }

        return null;
    }
}
