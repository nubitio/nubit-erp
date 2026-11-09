<?php

declare(strict_types=1);

namespace App\Identity\Invitation;

use App\Identity\Entity\Role;
use Nubit\AdminBundle\Identity\Entity\IdentityToken;

/** One outstanding invitation together with the role it will create the account with. */
final readonly class PendingInvitation
{
    private const int SUPER_ADMIN_ROLE_ID = 1;

    public function __construct(
        public IdentityToken $record,
        public ?Role $role,
    ) {}

    public function email(): string
    {
        return $this->record->getSubject();
    }

    /** Only a super administrator may handle an invitation that creates a super administrator. */
    public function requiresSuperAdmin(): bool
    {
        return $this->role?->getId() === self::SUPER_ADMIN_ROLE_ID;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->email(),
            'email' => $this->email(),
            'role' => $this->role === null ? null : ['id' => $this->role->getId(), 'name' => $this->role->getName()],
            'invitedBy' => $this->record->getCreatedBy(),
            'createdAt' => $this->record->getCreatedAt()->format(\DATE_ATOM),
            'expiresAt' => $this->record->getExpiresAt()->format(\DATE_ATOM),
        ];
    }
}
