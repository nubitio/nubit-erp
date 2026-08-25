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
use App\Billing\Recurring\Dto\GenerateSubscriptionChargeDto;
use App\Billing\Recurring\Repository\CustomerSubscriptionRepository;
use App\Billing\Recurring\State\GenerateSubscriptionChargeProcessor;
use App\Billing\Recurring\State\CustomerSubscriptionWriteProcessor;
use App\Contacts\Entity\Customer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    operations: [
        new GetCollection(security: "is_granted('perm:subscriptions.view')"),
        new Post(
            processor: CustomerSubscriptionWriteProcessor::class,
            security: "is_granted('perm:subscriptions.create')",
        ),
        new Get(security: "is_granted('perm:subscriptions.view')"),
        new Patch(
            processor: CustomerSubscriptionWriteProcessor::class,
            security: "is_granted('perm:subscriptions.edit')",
        ),
        new Delete(security: "is_granted('perm:subscriptions.cancel')"),
        new Post(
            uriTemplate: '/customer-subscriptions/{id}/generate',
            denormalizationContext: [],
            normalizationContext: ['groups' => ['customer_subscription_run:read']],
            input: GenerateSubscriptionChargeDto::class,
            output: CustomerSubscriptionRun::class,
            processor: GenerateSubscriptionChargeProcessor::class,
            security: "is_granted('perm:subscriptions.generate')",
            name: 'customer_subscription_generate',
        ),
    ],
    normalizationContext: ['groups' => ['customer_subscription:read']],
    denormalizationContext: ['groups' => ['customer_subscription:write']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class CustomerSubscription
{
    #[Groups(['customer_subscription:read', 'customer_subscription_run:read'])]
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

    #[Groups(['customer_subscription:read', 'customer_subscription:write', 'customer_subscription_run:read'])]
    #[ApiProperty(description: 'customer_subscription.service_reference.label')]
    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'service_reference', length: 255, nullable: true)]
    private ?string $serviceReference = null;

    #[Groups(['customer_subscription:read', 'customer_subscription:write'])]
    #[ApiProperty(description: 'customer_subscription.custom_description.label')]
    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'custom_description', length: 255, nullable: true)]
    private ?string $customDescription = null;

    #[Groups(['customer_subscription:read', 'customer_subscription:write'])]
    #[ApiProperty(description: 'customer_subscription.agreed_amount.label')]
    #[Assert\Positive]
    #[ORM\Column(name: 'agreed_amount', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $agreedAmount = null;

    #[Groups(['customer_subscription:read', 'customer_subscription:write'])]
    #[ApiProperty(description: 'customer_subscription.end_date.label')]
    #[Assert\GreaterThanOrEqual(propertyPath: 'startDate')]
    #[ORM\Column(name: 'end_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    /** @var Collection<int, CustomerSubscriptionLine> */
    #[Groups(['customer_subscription:read', 'customer_subscription:write'])]
    #[ORM\OneToMany(
        targetEntity: CustomerSubscriptionLine::class,
        mappedBy: 'subscription',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

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

    public function getServiceReference(): ?string
    {
        return $this->serviceReference;
    }

    public function setServiceReference(?string $serviceReference): static
    {
        $this->serviceReference = self::trimToNull($serviceReference);

        return $this;
    }

    public function getCustomDescription(): ?string
    {
        return $this->customDescription;
    }

    public function setCustomDescription(?string $customDescription): static
    {
        $this->customDescription = self::trimToNull($customDescription);

        return $this;
    }

    public function getAgreedAmount(): ?string
    {
        return $this->agreedAmount;
    }

    public function setAgreedAmount(?string $agreedAmount): static
    {
        $this->agreedAmount = self::trimToNull($agreedAmount);

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    /** @return Collection<int, CustomerSubscriptionLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(CustomerSubscriptionLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setSubscription($this);
        }

        return $this;
    }

    public function removeLine(CustomerSubscriptionLine $line): static
    {
        $this->lines->removeElement($line);

        return $this;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    private static function trimToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
