<?php

declare(strict_types=1);

namespace App\Efact\EventListener;

use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsEventListener(event: ConsoleCommandEvent::class, priority: 1024)]
#[AsEventListener(event: ConsoleTerminateEvent::class, priority: -1024)]
final readonly class ConsoleContextListener
{
    public function __construct(
        private TenantContext $tenantContext,
    ) {
    }

    public function __invoke(ConsoleCommandEvent|ConsoleTerminateEvent $event): void
    {
        if ($event instanceof ConsoleTerminateEvent) {
            $this->tenantContext->clear();

            return;
        }

        $command = $event->getCommand();
        $commandName = $command?->getName();

        $isScheduler = false;
        if ($command !== null) {
            $refClass = new \ReflectionClass($command);
            $isScheduler = $refClass->getAttributes(AsCronTask::class) !== [];
        }

        $channel = $isScheduler ? 'scheduler' : 'cli';
        $actor = $commandName !== null ? $channel . ':' . $commandName : $channel;

        $this->tenantContext->setActor($actor, $channel, $commandName);
    }
}
