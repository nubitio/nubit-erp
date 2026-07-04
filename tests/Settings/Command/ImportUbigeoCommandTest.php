<?php

declare(strict_types=1);

namespace App\Tests\Settings\Command;

use App\ControlPlane\Tenant\Service\TenantConnectionSwitcher;
use App\Settings\Command\ImportUbigeoCommand;
use App\Settings\Service\UbigeoImportService;
use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Tenant\Contract\TenantRegistryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class ImportUbigeoCommandTest extends TestCase
{
    public function testFailsWhenCsvIsUnreadable(): void
    {
        $importService = $this->createMock(UbigeoImportService::class);
        $importService->expects(self::never())->method('import');

        $tester = new CommandTester($this->newCommand($importService));
        $exitCode = $tester->execute(['file' => '/tmp/does-not-exist.csv']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('No se puede leer el archivo', $tester->getDisplay());
    }

    public function testImportsCsvSuccessfully(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ubigeo-cmd-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, "010101,AMAZONAS,CHACHAPOYAS,CHACHAPOYAS\n");

        $importService = $this->createMock(UbigeoImportService::class);
        $importService
            ->expects(self::once())
            ->method('import')
            ->with(
                filePath: $tmpFile,
                delimiter: ';',
                skipHeader: true,
                batchSize: 50,
                disableChecks: true,
                progressCallback: self::isCallable(),
            )
            ->willReturn(1);

        $tester = new CommandTester($this->newCommand($importService));
        $exitCode = $tester->execute([
            'file' => $tmpFile,
            '--delimiter' => ';',
            '--skip-header' => true,
            '--batch-size' => 50,
        ]);

        unlink($tmpFile);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Importación completada. Filas procesadas: 1', $tester->getDisplay());
    }

    public function testReturnsFailureWhenImportThrows(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ubigeo-cmd-fail-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, "010101,AMAZONAS,CHACHAPOYAS,CHACHAPOYAS\n");

        $importService = $this->createStub(UbigeoImportService::class);
        $importService->method('import')->willThrowException(new \RuntimeException('csv malformed'));

        $tester = new CommandTester($this->newCommand($importService));
        $exitCode = $tester->execute(['file' => $tmpFile]);

        unlink($tmpFile);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Error al importar: csv malformed', $tester->getDisplay());
    }

    private function newCommand(UbigeoImportService $importService): ImportUbigeoCommand
    {
        $tenantRegistry = $this->createStub(TenantRegistryInterface::class);
        $tenantRegistry
            ->method('getTenants')
            ->willReturn([['id' => 1, 'name' => 'test', 'primary_domain' => 'test.localhost']]);
        $tenantRegistry
            ->method('getTenantByName')
            ->willReturn(['id' => 1, 'name' => 'test', 'primary_domain' => 'test.localhost']);

        $command = new ImportUbigeoCommand($importService);
        $command->initDependencies(
            $tenantRegistry,
            $this->createMock(TenantConnectionSwitcher::class),
            new TenantContext(),
            $this->createMock(LoggerInterface::class),
        );

        return $command;
    }
}
