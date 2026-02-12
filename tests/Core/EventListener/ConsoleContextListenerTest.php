<?php

declare(strict_types=1);

namespace App\Tests\Core\EventListener;

use App\Core\Context\TenantContext;
use App\Core\EventListener\ConsoleContextListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

final class ConsoleContextListenerTest extends TestCase
{
    public function testCliCommandSetsCliChannel(): void
    {
        $ctx = new TenantContext();
        $listener = new ConsoleContextListener($ctx);

        $command = new Command('app:test:run');
        $event = new ConsoleCommandEvent($command, new ArrayInput([]), new NullOutput());

        $listener($event);

        $this->assertSame('cli', $ctx->getChannel());
        $this->assertSame('cli:app:test:run', $ctx->getActorIdentifier());
        $this->assertSame('app:test:run', $ctx->getCommandName());
    }

    public function testSchedulerCommandSetsSchedulerChannel(): void
    {
        $ctx = new TenantContext();
        $listener = new ConsoleContextListener($ctx);

        $command = new #[AsCronTask('* * * * *')] class ('app:scheduled:task') extends Command {};
        $event = new ConsoleCommandEvent($command, new ArrayInput([]), new NullOutput());

        $listener($event);

        $this->assertSame('scheduler', $ctx->getChannel());
        $this->assertSame('scheduler:app:scheduled:task', $ctx->getActorIdentifier());
        $this->assertSame('app:scheduled:task', $ctx->getCommandName());
    }

    public function testTerminateClearsContext(): void
    {
        $ctx = new TenantContext();
        $ctx->setActor('cli:app:test', 'cli', 'app:test');
        $ctx->setTenant(1, 'acme', 'acme.example.com', 'req-1');

        $listener = new ConsoleContextListener($ctx);

        $command = new Command('app:test');
        $event = new ConsoleTerminateEvent($command, new ArrayInput([]), new NullOutput(), 0);

        $listener($event);

        $this->assertNull($ctx->getActorIdentifier());
        $this->assertNull($ctx->getChannel());
        $this->assertNull($ctx->getCommandName());
        $this->assertNull($ctx->getTenantId());
    }

    public function testCommandWithNullNameUsesChannelOnly(): void
    {
        $ctx = new TenantContext();
        $listener = new ConsoleContextListener($ctx);

        $command = new Command();
        $event = new ConsoleCommandEvent($command, new ArrayInput([]), new NullOutput());

        $listener($event);

        $this->assertSame('cli', $ctx->getChannel());
        $this->assertSame('cli', $ctx->getActorIdentifier());
        $this->assertNull($ctx->getCommandName());
    }
}
