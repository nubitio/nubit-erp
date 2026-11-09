<?php

declare(strict_types=1);

namespace App\Identity\Invitation;

use App\Identity\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\AdminBundle\Identity\Entity\IdentityToken;
use Nubit\AdminBundle\Identity\InvitationService;

/** Read and manage invitations that have not been accepted, revoked or expired. */
final readonly class PendingInvitations
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        // Only a service while nubit_admin.identity is enabled; must stay optional or the
        // container would fail to compile with the module off.
        private ?InvitationService $invitations = null,
    ) {}

    public function enabled(): bool
    {
        return $this->invitations !== null;
    }

    /** @return list<PendingInvitation> newest first */
    public function all(): array
    {
        $result = [];
        foreach ($this->invitations?->outstanding() ?? [] as $record) {
            $result[] = new PendingInvitation($record, $this->roleOf($record->getRoles()));
        }

        return $result;
    }

    public function find(string $email): ?PendingInvitation
    {
        $email = strtolower(trim($email));
        foreach ($this->all() as $pending) {
            if ($pending->email() === $email) {
                return $pending;
            }
        }

        return null;
    }

    /** Issues a new link for the same e-mail and role; the bundle revokes the previous one. */
    public function resend(PendingInvitation $pending, ?string $invitedBy): IdentityToken
    {
        return (
            $this->invitations?->invite(
                $pending->email(),
                $pending->record->getRoles(),
                $invitedBy,
            )['record'] ?? throw new \LogicException('Invitations are not enabled.')
        );
    }

    public function revoke(PendingInvitation $pending): void
    {
        $this->invitations?->revoke($pending->email());
    }

    /** @param list<string> $roles the `role:<id>` entries carried by an invitation */
    private function roleOf(array $roles): ?Role
    {
        foreach ($roles as $entry) {
            $role = str_starts_with($entry, 'role:')
                ? $this->entityManager->find(Role::class, (int) substr($entry, 5))
                : null;
            if ($role instanceof Role) {
                return $role;
            }
        }

        return null;
    }
}
