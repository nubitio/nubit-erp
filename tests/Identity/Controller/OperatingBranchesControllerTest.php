<?php

declare(strict_types=1);

namespace App\Tests\Identity\Controller;

use App\Identity\Controller\OperatingBranchesController;
use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class OperatingBranchesControllerTest extends TestCase
{
    public function testReturnsAssignedBranchesWithTheDefaultFirst(): void
    {
        $repository = $this->createMock(BranchRepository::class);
        $repository
            ->expects(self::once())
            ->method('findBy')
            ->with(['isActive' => true], ['name' => 'ASC'])
            ->willReturn([
                $this->branch('a', 'Almacén'),
                $this->branch('b', 'Centro'),
                $this->branch('c', 'Principal'),
            ]);

        $user = new User();
        $user->assignOperatingBranches(['a', 'c'], 'c');
        $response = (new OperatingBranchesController($repository))($user);

        self::assertSame(
            [
                ['id' => 'c', 'name' => 'Principal', 'isDefault' => true],
                ['id' => 'a', 'name' => 'Almacén', 'isDefault' => false],
            ],
            json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    private function branch(string $id, string $name): Branch
    {
        $branch = new Branch()->setName($name);
        new ReflectionProperty(Branch::class, 'id')->setValue($branch, $id);

        return $branch;
    }
}
