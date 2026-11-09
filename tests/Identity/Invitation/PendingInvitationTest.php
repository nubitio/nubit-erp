<?php

declare(strict_types=1);

namespace App\Tests\Identity\Invitation;

use App\Identity\Entity\Role;
use App\Identity\Invitation\InvitationMessages;
use App\Identity\Invitation\PendingInvitation;
use DateTimeImmutable;
use Nubit\AdminBundle\Identity\Entity\IdentityToken;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class PendingInvitationTest extends TestCase
{
    public function testDescribesTheInvitationWithoutExposingTheTokenHash(): void
    {
        $invitation = new PendingInvitation($this->record('Ana@Example.test', 'abc123hash'), $this->role(3, 'Cajero'));

        $data = $invitation->toArray();

        self::assertSame('Ana@Example.test', $data['email']);
        self::assertSame(['id' => 3, 'name' => 'Cajero'], $data['role']);
        self::assertSame('admin', $data['invitedBy']);
        self::assertArrayHasKey('expiresAt', $data);
        self::assertStringNotContainsString('abc123hash', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function testAnInvitationWhoseRoleWasDeletedStillSerialises(): void
    {
        $data = new PendingInvitation($this->record('x@y.test', 'h'), null)->toArray();

        self::assertNull($data['role']);
    }

    public function testOnlyTheSuperAdministratorRoleNeedsASuperAdministrator(): void
    {
        self::assertTrue(
            new PendingInvitation(
                $this->record('a@b.test', 'h'),
                $this->role(1, 'Super Administrador'),
            )->requiresSuperAdmin(),
        );
        self::assertFalse(
            new PendingInvitation(
                $this->record('a@b.test', 'h'),
                $this->role(2, 'Administrador'),
            )->requiresSuperAdmin(),
        );
        self::assertFalse(new PendingInvitation($this->record('a@b.test', 'h'), null)->requiresSuperAdmin());
    }

    public function testMessagesAreTranslatedAndUnknownOnesPassThrough(): void
    {
        self::assertSame(
            'Ese correo ya tiene una cuenta.',
            InvitationMessages::translate('That address already has an account.'),
        );
        self::assertSame(
            'Escribe un correo válido.',
            InvitationMessages::translate('An invitation needs a valid email address.'),
        );
        self::assertSame('otra cosa', InvitationMessages::translate('otra cosa'));
    }

    private function record(string $email, string $hash): IdentityToken
    {
        $record = new IdentityToken(IdentityToken::PURPOSE_INVITATION, $email, $hash, new DateTimeImmutable('+7 days'));

        return $record->setRoles(['role:3'])->setCreatedBy('admin');
    }

    private function role(int $id, string $name): Role
    {
        $role = new Role()->setName($name);
        new ReflectionProperty(Role::class, 'id')->setValue($role, $id);

        return $role;
    }
}
