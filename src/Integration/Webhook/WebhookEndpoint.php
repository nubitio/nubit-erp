<?php

declare(strict_types=1);

namespace App\Integration\Webhook;

use App\Integration\Event\EventType;
use App\Integration\Repository\WebhookEndpointRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/**
 * A URL a tenant wants integration events delivered to, plus the shared secret
 * the HMAC signature is computed with.
 */
#[ORM\Entity(repositoryClass: WebhookEndpointRepository::class)]
#[ORM\Table(name: 'integration_webhook_endpoint')]
class WebhookEndpoint
{
    public const string STATUS_ENABLED = 'enabled';
    public const string STATUS_DISABLED = 'disabled';

    private const int AUTO_DISABLE_AFTER = 20;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column]
    private ?string $id = null;

    #[ORM\Column(length: 500)]
    private string $url;

    #[ORM\Column(length: 120)]
    private string $secret;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $subscribedTypes;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_ENABLED;

    #[ORM\Column]
    private int $consecutiveFailures = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $disabledReason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @param list<string> $subscribedTypes
     */
    public function __construct(string $url, #[\SensitiveParameter] string $secret, array $subscribedTypes)
    {
        $this->url = $url;
        $this->secret = $secret;
        $this->subscribedTypes = [] === $subscribedTypes ? ['*'] : $subscribedTypes;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function rotateSecret(#[\SensitiveParameter] string $secret): void
    {
        $this->secret = $secret;
    }

    /** @return list<string> */
    public function getSubscribedTypes(): array
    {
        return $this->subscribedTypes;
    }

    public function isEnabled(): bool
    {
        return self::STATUS_ENABLED === $this->status;
    }

    public function getDisabledReason(): ?string
    {
        return $this->disabledReason;
    }

    public function getConsecutiveFailures(): int
    {
        return $this->consecutiveFailures;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function wantsEvent(EventType $type): bool
    {
        foreach ($this->subscribedTypes as $pattern) {
            if ($type->matches($pattern)) {
                return true;
            }
        }

        return false;
    }

    public function recordSuccess(): void
    {
        $this->consecutiveFailures = 0;
    }

    /**
     * @return bool whether this failure tipped the endpoint into auto-disabled
     */
    public function recordFailure(): bool
    {
        ++$this->consecutiveFailures;
        if ($this->consecutiveFailures >= self::AUTO_DISABLE_AFTER && self::STATUS_ENABLED === $this->status) {
            $this->status = self::STATUS_DISABLED;
            $this->disabledReason = sprintf('auto-disabled after %d consecutive failures', $this->consecutiveFailures);

            return true;
        }

        return false;
    }

    public function enable(): void
    {
        $this->status = self::STATUS_ENABLED;
        $this->disabledReason = null;
        $this->consecutiveFailures = 0;
    }

    public function disable(string $reason): void
    {
        $this->status = self::STATUS_DISABLED;
        $this->disabledReason = $reason;
    }
}
