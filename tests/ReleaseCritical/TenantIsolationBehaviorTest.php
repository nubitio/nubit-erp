<?php

declare(strict_types=1);

namespace App\Tests\ReleaseCritical;

use App\BankReconciliation\Message\BankStatementImportMessage;
use App\BankReconciliation\MessageHandler\BankStatementImportMessageHandler;
use App\BankReconciliation\Service\BankStatementParserService;
use App\Efact\EventListener\ConsoleContextListener;
use App\Efact\EventListener\TenantListener;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Tenant\Contract\TenantConnectionSwitcherInterface;
use Nubit\Platform\Tenant\Contract\TenantRegistryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Mercure\HubInterface;

final class TenantIsolationBehaviorTest extends TestCase
{
    public function testHttpSwitchesBetweenTenantsAndClearsFailedResolution(): void
    {
        $registry = $this->createMock(TenantRegistryInterface::class);
        $registry
            ->expects(static::exactly(2))
            ->method('getTenantByDomain')
            ->willReturnMap([
                ['alpha.example.test', ['id' => 10, 'name' => 'alpha', 'status' => 'active']],
                ['unknown.example.test', null],
            ]);
        $registry->expects(static::once())->method('getTenantByName')->with('unknown')->willReturn(null);

        $switcher = $this->createMock(TenantConnectionSwitcherInterface::class);
        $switcher->expects(static::once())->method('switchConnection')->with('alpha');
        $context = new TenantContext();
        $listener = new TenantListener($switcher, $registry, $context, new NullLogger(), 'test');

        $listener(
            new RequestEvent(
                $this->createMock(HttpKernelInterface::class),
                Request::create('https://alpha.example.test/api/products'),
                HttpKernelInterface::MAIN_REQUEST,
            ),
        );
        static::assertSame('alpha', $context->getTenantName());

        $failed = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('https://unknown.example.test/api/products'),
            HttpKernelInterface::MAIN_REQUEST,
        );
        $listener($failed);

        static::assertSame(404, $failed->getResponse()?->getStatusCode());
        static::assertNull($context->getTenantName(), 'a failed request must not inherit tenant alpha');
    }

    public function testCliContextIsScopedToOneCommand(): void
    {
        $context = new TenantContext();
        $listener = new ConsoleContextListener($context);
        $command = new Command('tenant:health-check');
        $commandEvent = new ConsoleCommandEvent(
            $command,
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput(),
        );

        $listener($commandEvent);
        static::assertSame('cli:tenant:health-check', $context->getActorIdentifier());
        $listener(new ConsoleTerminateEvent($command, $commandEvent->getInput(), $commandEvent->getOutput(), 0));
        static::assertNull($context->getActorIdentifier());
        static::assertNull($context->getTenantName());
    }

    public function testAsyncMessageSwitchesToItsTenantInsteadOfReusingWorkerContext(): void
    {
        $context = new TenantContext();
        $context->setTenant(10, 'alpha', 'https://alpha.example.test', 'request-a');
        $switcher = $this->createMock(TenantConnectionSwitcherInterface::class);
        $switchCalls = [];
        $switcher
            ->expects(static::exactly(2))
            ->method('switchConnection')
            ->willReturnCallback(static function (string $tenant) use (&$switchCalls): void {
                $switchCalls[] = $tenant;
            });

        $handler = new BankStatementImportMessageHandler(
            new BankStatementParserService($this->createMock(EntityManagerInterface::class)),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(HubInterface::class),
            new NullLogger(),
            $context,
            $switcher,
        );
        $ensureContext = new \ReflectionMethod($handler, 'ensureTenantContext');

        $beta = new BankStatementImportMessage(
            'import-b',
            '/tmp/missing',
            'csv',
            'https://beta.example.test',
            'beta',
            20,
            'https://beta.example.test',
            'request-b',
        );
        static::assertTrue($ensureContext->invoke($handler, $beta));
        static::assertSame('beta', $context->getTenantName());

        $alpha = new BankStatementImportMessage(
            'import-a',
            '/tmp/missing',
            'csv',
            'https://alpha.example.test',
            'alpha',
            10,
            'https://alpha.example.test',
            'request-a',
        );
        static::assertTrue($ensureContext->invoke($handler, $alpha));
        static::assertSame('alpha', $context->getTenantName());
        static::assertSame(['beta', 'alpha'], $switchCalls);
    }
}
