<?php

declare(strict_types=1);

namespace App\Integration\Entity;

use App\Integration\Repository\ApiKeyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/**
 * One credential for an {@see ApiClient}.
 *
 * The secret itself is never stored. What is kept is a public `prefix` (the
 * indexed selector used to find the row) and `tokenHash`, a SHA-256 of the
 * full key. SHA-256 rather than a password hash is deliberate: the key is a
 * 190-bit random string, so there is nothing to brute-force and the check has
 * to be fast enough to run on every request.
 */
#[ORM\Entity(repositoryClass: ApiKeyRepository::class)]
#[ORM\Table(name: 'integration_api_key')]
#[ORM\UniqueConstraint(name: 'uq_integration_api_key_prefix', columns: ['prefix'])]
class ApiKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: ApiClient::class, inversedBy: 'keys')]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ApiClient $client;

    #[ORM\Column(length: 40)]
    private string $prefix;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(ApiClient $client, string $prefix, string $tokenHash)
    {
        $this->client = $client;
        $this->prefix = $prefix;
        $this->tokenHash = $tokenHash;
        $this->createdAt = new \DateTimeImmutable();
        $client->addKey($this);
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getClient(): ApiClient
    {
        return $this->client;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt instanceof \DateTimeImmutable;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(?\DateTimeImmutable $at = null): void
    {
        $this->revokedAt ??= $at ?? new \DateTimeImmutable();
    }

    /**
     * Record use, but only when the stored timestamp is stale enough to be
     * worth a write — the authenticator calls this on every request.
     */
    public function touch(\DateTimeImmutable $now, int $granularitySeconds = 60): bool
    {
        if (
            $this->lastUsedAt instanceof \DateTimeImmutable
            && ($now->getTimestamp() - $this->lastUsedAt->getTimestamp()) < $granularitySeconds
        ) {
            return false;
        }

        $this->lastUsedAt = $now;

        return true;
    }
}
