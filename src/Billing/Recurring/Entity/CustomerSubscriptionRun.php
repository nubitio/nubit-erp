<?php

declare(strict_types=1);

namespace App\Billing\Recurring\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Billing\Recurring\Repository\CustomerSubscriptionRunRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CustomerSubscriptionRunRepository::class)]
#[ORM\Table(name: 'customer_subscription_run')]
#[ORM\UniqueConstraint(name: 'uq_subscription_run_period', columns: ['subscription_id', 'period_start', 'period_end'])]
#[ORM\Index(name: 'idx_subscription_run_created', columns: ['created_at'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('perm:subscriptions.view')"),
        new Get(security: "is_granted('perm:subscriptions.view')"),
    ],
    normalizationContext: ['groups' => ['customer_subscription_run:read']],
    filters: [DataGridFilter::class],
)]
class CustomerSubscriptionRun
{
    #[Groups(['customer_subscription_run:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Groups(['customer_subscription_run:read'])]
    #[ApiProperty(readableLink: true)]
    #[ORM\ManyToOne(targetEntity: CustomerSubscription::class)]
    #[ORM\JoinColumn(name: 'subscription_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private CustomerSubscription $subscription;

    #[Groups(['customer_subscription_run:read'])]
    #[ORM\Column(name: 'period_start', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $periodStart;

    #[Groups(['customer_subscription_run:read'])]
    #[ORM\Column(name: 'period_end', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $periodEnd;

    #[Groups(['customer_subscription_run:read'])]
    #[ORM\Column(name: 'source', length: 20)]
    private string $source;

    #[Groups(['customer_subscription_run:read'])]
    #[ORM\Column(name: 'status', length: 20)]
    private string $status = 'generated';

    #[Groups(['customer_subscription_run:read'])]
    #[ORM\Column(name: 'sale_id', type: Types::STRING, length: 36)]
    private string $saleId;

    #[Groups(['customer_subscription_run:read'])]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getSubscription(): CustomerSubscription { return $this->subscription; }
    public function setSubscription(CustomerSubscription $subscription): static { $this->subscription = $subscription; return $this; }
    public function getPeriodStart(): \DateTimeImmutable { return $this->periodStart; }
    public function setPeriodStart(\DateTimeImmutable $periodStart): static { $this->periodStart = $periodStart; return $this; }
    public function getPeriodEnd(): \DateTimeImmutable { return $this->periodEnd; }
    public function setPeriodEnd(\DateTimeImmutable $periodEnd): static { $this->periodEnd = $periodEnd; return $this; }
    public function getSource(): string { return $this->source; }
    public function setSource(string $source): static { $this->source = $source; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getSaleId(): string { return $this->saleId; }
    public function setSaleId(string $saleId): static { $this->saleId = $saleId; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
