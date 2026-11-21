<?php

declare(strict_types=1);

namespace App\Identity\Directory;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use App\Identity\Entity\User;
use App\Identity\Invitation\PendingInvitation;
use App\Identity\Invitation\PendingInvitations;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;

/**
 * The people of the tenant in one list: pending invitations first, then the users. Paging, filtering
 * and sorting happen here, on the server, so the grid works the same at any size. Invitations are not
 * users (the identity module keeps them as tokens until they are accepted), so this is a read model:
 * creating, editing and deleting still go to `/api/users`.
 */
final readonly class UserDirectory
{
    public const string ACTIVE = 'active';
    public const string INVITED = 'invited';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataGridFilter $gridFilter,
        private PendingInvitations $pending,
        private InvitationMatcher $matcher = new InvitationMatcher(),
    ) {}

    /** @return array{member: list<array<string, mixed>>, totalItems: int} */
    public function page(DirectoryQuery $query): array
    {
        $invitations = $query->wants(self::INVITED) ? $this->invitations($query) : [];
        $window = DirectoryWindow::for($query->offset(), $query->perPage, count($invitations));

        $members = array_map(
            self::invitationRow(...),
            array_slice($invitations, $window->invitationOffset, $window->invitationLimit),
        );
        $usersTotal = 0;
        if ($query->wants(self::ACTIVE)) {
            $paginator = $this->users($query, $window);
            $usersTotal = count($paginator);
            if ($window->userLimit > 0) {
                foreach ($paginator as $user) {
                    $members[] = self::userRow($user);
                }
            }
        }

        return ['member' => $members, 'totalItems' => count($invitations) + $usersTotal];
    }

    /** @return list<PendingInvitation> */
    private function invitations(DirectoryQuery $query): array
    {
        $matching = array_values(array_filter(
            $this->pending->all(),
            fn(PendingInvitation $invitation): bool => $this->matcher->matches($invitation, $query),
        ));

        // Newest first, unless the grid sorts by something an invitation has.
        $sorted = $query->sort[0] ?? ['selector' => null, 'desc' => false];
        $key = match ($sorted['selector']) {
            'username', 'email' => static fn(PendingInvitation $i): string => strtolower($i->email()),
            'role' => static fn(PendingInvitation $i): string => strtolower($i->role?->getName() ?? ''),
            default => null,
        };
        if ($key !== null) {
            usort(
                $matching,
                static fn(PendingInvitation $a, PendingInvitation $b): int => (
                    ($sorted['desc'] ? -1 : 1) * strcmp($key($a), $key($b))
                ),
            );
        }

        return $matching;
    }

    /** @return Paginator<User> */
    private function users(DirectoryQuery $query, DirectoryWindow $window): Paginator
    {
        $builder = $this->entityManager
            ->createQueryBuilder()
            ->select('o', 'r')
            ->from(User::class, 'o')
            ->leftJoin('o.role', 'r');

        $this->gridFilter->apply($builder, new QueryNameGenerator(), User::class, null, [
            'filters' => $query->userFilters(),
        ]);
        // Whatever the sort, the id keeps the pages stable.
        $builder->addOrderBy('o.id', 'ASC')->setFirstResult($window->userOffset)->setMaxResults($window->userLimit);

        return new Paginator($builder);
    }

    /** @return array<string, mixed> */
    private static function userRow(User $user): array
    {
        $role = $user->getRole();

        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'phone' => $user->getPhone(),
            'role' => $role === null ? null : ['id' => $role->getId(), 'name' => $role->getName()],
            'lastLoginAt' => $user->getLastLoginAt()?->format(\DATE_ATOM),
            'status' => self::ACTIVE,
        ];
    }

    /** @return array<string, mixed> */
    private static function invitationRow(PendingInvitation $invitation): array
    {
        $data = $invitation->toArray();

        return [
            'id' => 'invitation:' . $invitation->email(),
            'username' => $invitation->email(),
            'email' => $invitation->email(),
            'displayName' => null,
            'phone' => null,
            'role' => $data['role'],
            'lastLoginAt' => null,
            'status' => self::INVITED,
            'expiresAt' => $data['expiresAt'],
            'invitedBy' => $data['invitedBy'],
        ];
    }
}
