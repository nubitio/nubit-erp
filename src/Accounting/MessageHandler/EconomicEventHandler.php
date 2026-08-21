<?php

declare(strict_types=1);

namespace App\Accounting\MessageHandler;

use App\Accounting\Message\EconomicEvent;
use App\Accounting\Service\DefaultPostingService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EconomicEventHandler
{
    public function __construct(
        private DefaultPostingService $postingService,
    ) {}

    public function __invoke(EconomicEvent $event): void
    {
        $this->postingService->post($event);
    }
}
