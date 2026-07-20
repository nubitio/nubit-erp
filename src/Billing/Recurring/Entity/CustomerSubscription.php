<?php

declare(strict_types=1);

namespace App\Billing\Recurring\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Billing\Recurring\Repository\CustomerSubscriptionRepository;
use App\Contacts\Entity\Customer;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CustomerSubscriptionRepository::class)]
#[ORM\Table(name: 'customer_subscription')]
#[ORM\Index(name: 'idx_customer_subscription_status_next_billing', columns: ['status', 'next_billing_date'])]
#[ApiResource(
    operations: [new GetCollection(), new Post(), new Get(), new Patch(), new Delete()],
    normalizationContext: ['groups' => ['customer_subscription:read']],
    denormalizationContext: ['groups' => ['customer_subscription:write']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class CustomerSubscription
{
    #[Groups(['customer_subscription:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Assert\NotNull]
    #[Groups(['customer_subscription:read', 'customer_subscription:write'])]
    #[ApiProperty(description: 'customer_subscription.customer.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: false)]
    private Customer $customer;

    #[Assert\NotNull]
    #[Groups(['customer_subscription:read', 'customer_subscription:write'])]
    #[ApiProperty(description: 'customer_subscription.plan.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: SubscriptionPlan::class)]
    #[ORM\JoinColumn(name: 'plan_id', referencedColumnName: 'id', nullable: false)]
    private SubscriptionPlan $plan;

    #[Groups(['customer_subscription:read', 'customer_subscription:write'])]
    #[ApiProperty(description: 'customer_subscription.status.label')]
    #[ORM\Column(name: 'status', length: 20, options: ['default' => 'active'])]
    private string $status = 'active';

    #[Assert\NotNull]
    #[Groups(['customer_subscription:read', 'customer_subscription:write'])]
    #[ApiProperty(description: 'customer_subscription.start_date.label')]
    #[ORM\Column(name: 'start_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    #[Groups(['customer_subscription:read'])]
    #[ApiProperty(description: 'customer_subscription.current_period_start.label')]
    #[ORM\Column(name: 'current_period_start', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $currentPeriodStart = null;

    #[Groups(['customer_subscription:read'])]
    #[ApiProperty(description: 'customer_subscription.current_period_end.label')]
    #[ORM\Column(name: 'current_period_end', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $currentPeriodEnd = null;

    #[Assert\NotNull]
    #[Groups(['customer_subscription:read', 'customer_subscription:write'])]
    #[ApiProperty(description: 'customer_subscription.next_billing_date.label')]
    #[ORM\Column(name: 'next_billing_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $nextBillingDate;

    #[Groups(['customer_subscription:read', 'customer_subscription:write'])]
    #[ApiProperty(description: 'customer_subscription.auto_renew.label')]
    #[ORM\Column(name: 'auto_renew', options: ['default' => true])]
    private bool $autoRenew = true;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getPlan(): SubscriptionPlan
    {
        return $this->plan;
    }

    public function setPlan(SubscriptionPlan $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getCurrentPeriodStart(): ?\DateTimeImmutable
    {
        return $this->currentPeriodStart;
    }

    public function setCurrentPeriodStart(?\DateTimeImmutable $currentPeriodStart): static
    {
        $this->currentPeriodStart = $currentPeriodStart;

        return $this;
    }

    public function getCurrentPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->currentPeriodEnd;
    }

    public function setCurrentPeriodEnd(?\DateTimeImmutable $currentPeriodEnd): static
    {
        $this->currentPeriodEnd = $currentPeriodEnd;

        return $this;
    }

    public function getNextBillingDate(): \DateTimeImmutable
    {
        return $this->nextBillingDate;
    }

    public function setNextBillingDate(\DateTimeImmutable $nextBillingDate): static
    {
        $this->nextBillingDate = $nextBillingDate;

        return $this;
    }

    public function isAutoRenew(): bool
    {
        return $this->autoRenew;
    }

    public function setAutoRenew(bool $autoRenew): static
    {
        $this->autoRenew = $autoRenew;

        return $this;
    }
}
