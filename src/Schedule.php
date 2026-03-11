<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
readonly class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return new SymfonySchedule()
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run

            // Daily AI morning briefing — runs at 07:30 America/Lima every day.
            // RunCommandMessage dispatches "app:ai:briefing:generate" via Symfony Messenger.
            ->add(
                RecurringMessage::cron(
                    '30 7 * * *',
                    new RunCommandMessage('app:ai:briefing:generate', throwOnFailure: false),
                    'America/Lima',
                )
            )
        ;
    }
}
