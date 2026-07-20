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
    ) {}

    public function getSchedule(): SymfonySchedule
    {
        return new SymfonySchedule()
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run
            // Daily AI morning briefing — runs at 07:30 America/Lima every day.
            // RunCommandMessage dispatches "app:ai:briefing:generate" via Symfony Messenger.
            ->add(RecurringMessage::cron(
                '30 7 * * *',
                new RunCommandMessage('app:ai:briefing:generate', throwOnFailure: false),
                'America/Lima',
            ))
            // Stock-low in-app notifications — runs every 6 hours.
            // Emits stock_low notifications for all products at or below min_stock,
            // deduplicated per user within a 24-hour sliding window.
            ->add(RecurringMessage::cron(
                '0 */6 * * *',
                new RunCommandMessage('app:notification:generate-stock', throwOnFailure: false),
                'America/Lima',
            ))
            // Recurring billing — generates a pending sale for every subscription
            // whose billing period is due, runs daily at 06:00 America/Lima.
            ->add(RecurringMessage::cron(
                '0 6 * * *',
                new RunCommandMessage('app:billing:generate-recurring-sales', throwOnFailure: false),
                'America/Lima',
            ))
            // Due/overdue sale notifications — runs daily at 08:00 America/Lima.
            ->add(RecurringMessage::cron(
                '0 8 * * *',
                new RunCommandMessage('app:billing:notify-due-sales', throwOnFailure: false),
                'America/Lima',
            ));
    }
}
