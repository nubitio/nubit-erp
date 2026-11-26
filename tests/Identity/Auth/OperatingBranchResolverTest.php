<?php

declare(strict_types=1);

namespace App\Tests\Identity\Auth;

use App\Identity\Auth\OperatingBranchResolver;
use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use PHPUnit\Framework\TestCase;

final class OperatingBranchResolverTest extends TestCase
{
    public function testClosingOrRemovingAccessInvalidatesAnExistingSelection(): void
    {
        $branch = new Branch();
        $branches = $this->createStub(BranchRepository::class);
        $branches->method('find')->willReturn($branch);
        $resolver = new OperatingBranchResolver($branches);
        $user = new User();
        $user->assignOperatingBranches(['a'], 'a');

        self::assertSame($branch, $resolver->resolve($user, 'a'));

        $branch->setIsActive(false);
        self::assertNull($resolver->resolve($user, 'a'));

        $branch->setIsActive(true);
        $user->assignOperatingBranches(['b'], 'b');
        self::assertNull($resolver->resolve($user, 'a'));
    }
}
