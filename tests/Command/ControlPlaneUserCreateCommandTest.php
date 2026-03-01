<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ControlPlaneUserCreateCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ControlPlaneUserCreateCommandTest extends TestCase
{
    public function testFailsOnInvalidUsernameAndMissingPassword(): void
    {
        $command = new ControlPlaneUserCreateCommand($this->registryFor($this->connectionWithTable(true)));
        $tester = new CommandTester($command);

        $invalidUsernameCode = $tester->execute(['username' => 'bad user', '--password' => 'verylongpassword']);
        self::assertSame(Command::FAILURE, $invalidUsernameCode);
        self::assertStringContainsString('Username must be 3-64 chars', $tester->getDisplay());

        $missingPasswordCode = $tester->execute(['username' => 'admin.user'], ['interactive' => false]);
        self::assertSame(Command::FAILURE, $missingPasswordCode);
        self::assertStringContainsString('Password is required in non-interactive mode', $tester->getDisplay());
    }

    public function testFailsWhenPasswordIsTooShortOrTableMissing(): void
    {
        $command = new ControlPlaneUserCreateCommand($this->registryFor($this->connectionWithTable(true)));
        $tester = new CommandTester($command);

        $shortPasswordCode = $tester->execute(['username' => 'admin', '--password' => 'short']);
        self::assertSame(Command::FAILURE, $shortPasswordCode);
        self::assertStringContainsString('Password must be at least 10 characters', $tester->getDisplay());

        $missingTableCommand = new ControlPlaneUserCreateCommand($this->registryFor($this->connectionWithTable(false)));
        $missingTableTester = new CommandTester($missingTableCommand);
        $missingTableCode = $missingTableTester->execute(['username' => 'admin', '--password' => 'longpassword']);

        self::assertSame(Command::FAILURE, $missingTableCode);
        self::assertStringContainsString('table does not exist', $missingTableTester->getDisplay());
    }

    public function testCreatesOrUpdatesControlPlaneUserSuccessfully(): void
    {
        $connection = $this->connectionWithTable(true);
        $connection->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('INSERT INTO control_plane_admin_users'),
                self::callback(static function (array $params): bool {
                    return $params[0] === 'admin.user'
                        && is_string($params[1])
                        && $params[1] !== ''
                        && $params[2] === json_encode(['ROLE_ADMIN'])
                        && $params[3] === 0;
                })
            );

        $command = new ControlPlaneUserCreateCommand($this->registryFor($connection));
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'username' => ' Admin.User ',
            '--password' => 'mylongpassword',
            '--roles' => ',',
            '--inactive' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Control plane user "admin.user" saved (inactive).', $tester->getDisplay());
    }

    private function registryFor(Connection $connection): ConnectionRegistry
    {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('control_plane')->willReturn($connection);

        return $registry;
    }

    /**
     * @return Connection&MockObject
     */
    private function connectionWithTable(bool $tableExists): Connection
    {
        $schema = $this->createMock(AbstractSchemaManager::class);
        $schema->method('tablesExist')->with(['control_plane_admin_users'])->willReturn($tableExists);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schema);

        return $connection;
    }
}
