<?php

declare(strict_types=1);

namespace App\Billing\Despatch\MessageHandler;

use App\Billing\Despatch\Domain\DespatchSendUseCase;
use App\Billing\Despatch\Message\SendDespatchMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendDespatchMessageHandler
{
    public function __construct(
        private DespatchSendUseCase $despatchSendUseCase,
        private LoggerInterface $billingLogger,
    ) {
    }

    public function __invoke(SendDespatchMessage $message): void
    {
        $tenantId = $message->getTenantId() ?: 'unknown';

        $this->billingLogger->info('send_despatch.start', [
            'tenant_id' => $tenantId,
            'despatch_guide_id' => $message->despatchGuideId,
        ]);

        try {
            $this->despatchSendUseCase->__invoke($message->despatchGuideId);
        } catch (\Throwable $e) {
            $this->billingLogger->error('send_despatch.failed', [
                'tenant_id' => $tenantId,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->billingLogger->info('send_despatch.done', [
            'tenant_id' => $tenantId,
            'despatch_guide_id' => $message->despatchGuideId,
        ]);
    }
}
