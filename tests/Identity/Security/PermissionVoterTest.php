<?php

declare(strict_types=1);

namespace App\Tests\Identity\Security;

use App\Identity\Security\PermissionCheckerInterface;
use App\Identity\Security\PermissionVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class PermissionVoterTest extends TestCase
{
    private function makeVoter(bool $checkerResult): PermissionVoter
    {
        $checker = $this->createStub(PermissionCheckerInterface::class);
        $checker->method('hasPermission')->willReturn($checkerResult);

        return new PermissionVoter($checker);
    }

    public function testAbstainsForAttributesWithoutPrefix(): void
    {
        $voter = $this->makeVoter(true);

        static::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new NullToken(), null, ['ROLE_ADMIN']));
    }

    public function testGrantsWhenCheckerGrants(): void
    {
        $voter = $this->makeVoter(true);

        static::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(new NullToken(), null, ['perm:sales.create']));
    }

    public function testDeniesWhenCheckerDenies(): void
    {
        $voter = $this->makeVoter(false);

        static::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), null, ['perm:sales.create']));
    }
}
