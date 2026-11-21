<?php

declare(strict_types=1);

namespace App\Tests\Identity\Directory;

use App\Identity\Directory\DirectoryQuery;
use App\Identity\Directory\InvitationMatcher;
use App\Identity\Entity\Role;
use App\Identity\Invitation\PendingInvitation;
use DateTimeImmutable;
use Nubit\AdminBundle\Identity\Entity\IdentityToken;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class InvitationMatcherTest extends TestCase
{
    private InvitationMatcher $matcher;
    private PendingInvitation $ana;

    protected function setUp(): void
    {
        $this->matcher = new InvitationMatcher();
        $this->ana = $this->invitation('Ana@Example.test', 3, 'Cajero');
    }

    public function testMatchesTheEmailAsTheUsernameWithoutCase(): void
    {
        static::assertTrue($this->hits([['username', 'contains', 'ana@']]));
        static::assertTrue($this->hits([['username', 'startswith', 'ANA']]));
        static::assertTrue($this->hits([['username', 'endswith', 'example.test']]));
        static::assertTrue($this->hits([['username', '=', 'ana@example.test']]));
        static::assertFalse($this->hits([['username', 'contains', 'luis']]));
        static::assertFalse($this->hits([['username', 'notcontains', 'ana']]));
    }

    public function testMatchesTheRoleByIdOrByIri(): void
    {
        static::assertTrue($this->hits([['role', '=', '3']]));
        static::assertTrue($this->hits([['role', '=', '/api/roles/3']]));
        static::assertFalse($this->hits([['role', '=', '/api/roles/2']]));
        static::assertTrue($this->hits([['role', 'in', ['/api/roles/2', '/api/roles/3']]]));
    }

    public function testAnInvitationHasNoNameOrPhoneOrSignInYet(): void
    {
        static::assertFalse($this->hits([['displayName', 'contains', 'a']]));
        static::assertFalse($this->hits([['lastLoginAt', 'isnotnull']]));
        static::assertTrue($this->hits([['lastLoginAt', 'isnull']]));
        static::assertTrue($this->hits([['phone', 'isnull']]));
    }

    public function testIgnoresWhatTheUsersFilterIgnores(): void
    {
        // `status` is decided before; an unknown column is dropped, as the users' filter drops it.
        static::assertTrue($this->hits([
            ['status',   '=', 'invited'],
            ['nonsense', '=', 'x'],
        ]));
    }

    public function testSearchLooksOnlyInTheFieldsAnInvitationHas(): void
    {
        static::assertTrue($this->hits([], 'EXAMPLE', ['username', 'displayName']));
        static::assertFalse($this->hits([], 'Ana', ['displayName', 'phone']));
        static::assertTrue($this->hits([], 'zzz', []), 'without columns to search the grid does not search');
    }

    public function testEveryCriterionMustHold(): void
    {
        static::assertFalse($this->hits([
            ['username', 'contains', 'ana'],
            ['role',     '=',        '2'],
        ]));
        static::assertTrue($this->hits([
            ['username', 'contains', 'ana'],
            ['role',     '=',        '3'],
        ]));
    }

    /**
     * @param list<array{0: string, 1: string, 2?: mixed}> $leaves
     * @param list<string>                                  $searchFields
     */
    private function hits(array $leaves, ?string $search = null, array $searchFields = []): bool
    {
        $leaves = array_map(static fn(array $leaf): array => [$leaf[0], $leaf[1], $leaf[2] ?? null], $leaves);

        return $this->matcher->matches(
            $this->ana,
            new DirectoryQuery(leaves: $leaves, search: $search, searchFields: $searchFields),
        );
    }

    private function invitation(string $email, int $roleId, string $roleName): PendingInvitation
    {
        $record = new IdentityToken(
            IdentityToken::PURPOSE_INVITATION,
            $email,
            'hash',
            new DateTimeImmutable('+7 days'),
        );
        $role = new Role()->setName($roleName);
        new ReflectionProperty(Role::class, 'id')->setValue($role, $roleId);

        return new PendingInvitation($record->setRoles(['role:' . $roleId])->setCreatedBy('admin'), $role);
    }
}
