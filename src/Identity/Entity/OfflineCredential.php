<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A key a user's till holds to sign sales made without connection. Only the row is stored:
 * the secret is derived from it (see {@see \App\Identity\Offline\OfflineSignature}).
 * Revoking sets `revokedAt`; sales signed before that moment are still honoured, because
 * the customer already has the ticket. Times are Unix seconds, the unit the signature uses,
 * so no time zone can shift them.
 */
#[ORM\Entity]
#[ORM\Table(name: 'identity_offline_credential')]
#[ORM\Index(name: 'IDX_identity_offline_credential_user', columns: ['user_id', 'expires_at'])]
class OfflineCredential
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $id;

    #[ORM\Column(name: 'user_id')]
    private int $userId;

    #[ORM\Column(name: 'issued_at', type: Types::INTEGER)]
    private int $issuedAt;

    #[ORM\Column(name: 'expires_at', type: Types::INTEGER)]
    private int $expiresAt;

    #[ORM\Column(name: 'revoked_at', type: Types::INTEGER, nullable: true)]
    private ?int $revokedAt = null;

    public function __construct(string $id, int $userId, int $issuedAt, int $expiresAt)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->issuedAt = $issuedAt;
        $this->expiresAt = $expiresAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getIssuedAt(): int
    {
        return $this->issuedAt;
    }

    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    public function getRevokedAt(): ?int
    {
        return $this->revokedAt;
    }

    public function revoke(int $at): void
    {
        $this->revokedAt ??= $at;
    }
}
