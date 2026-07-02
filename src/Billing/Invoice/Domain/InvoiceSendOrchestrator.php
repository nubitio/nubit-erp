<?php

declare(strict_types=1);

namespace App\Billing\Invoice\Domain;

use Psr\Log\LoggerInterface;
use Throwable;

readonly class InvoiceSendOrchestrator
{
    public function __construct(
        private InvoiceSendUseCase $invoiceSendUseCase,
        private InvoiceSendRetryService $retryService,
        private SunatSendControlService $controlService,
        private InvoiceSendRecoveryService $recoveryService,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array{max_attempts:int,base_seconds:int,max_seconds:int} $retryPolicy
     */
    public function process(
        string $taxDocumentId,
        array $retryPolicy,
        int $rejectionThreshold,
        ?string $tenantName,
    ): InvoiceSendProcessResult {
        $retryEvaluation = $this->retryService->evaluateRetryEligibility($taxDocumentId, $retryPolicy);
        if (!$retryEvaluation['eligible']) {
            $this->retryService->recordRetrySkipEvent(
                $taxDocumentId,
                $retryEvaluation['reason'],
                $retryEvaluation['attempts'],
                $retryEvaluation['wait_seconds'],
            );

            return new InvoiceSendProcessResult(
                sent: false,
                error: null,
                paused: false,
                skipReason: $retryEvaluation['reason'],
            );
        }

        try {
            $response = $this->invoiceSendUseCase->__invoke($taxDocumentId);
            $currentStatus = $this->retryService->getCurrentStatus($taxDocumentId);
            if ($response->sunatResponse->isSuccess() || $currentStatus === '0') {
                $this->controlService->resetRejectionStreak($tenantName);

                return new InvoiceSendProcessResult(sent: true, error: null, paused: false, skipReason: null);
            }

            $errorCode = $response->sunatResponse->getError()?->getCode();
            $message = $response->sunatResponse->getError()?->getMessage() ?? 'Unknown error';
            $this->logger->warning('Invoice sending warning', [
                'tax_document_id' => $taxDocumentId,
                'code' => $errorCode,
                'message' => $message,
            ]);

            if (!$this->controlService->isSunatRejection($errorCode, $message)) {
                $this->controlService->resetRejectionStreak($tenantName);

                return new InvoiceSendProcessResult(sent: false, error: $message, paused: false, skipReason: null);
            }

            $streak = $this->controlService->incrementRejectionStreak($tenantName);
            $this->logger->warning('SUNAT rejection streak incremented', [
                'tax_document_id' => $taxDocumentId,
                'code' => $errorCode,
                'streak' => $streak,
                'threshold' => $rejectionThreshold,
            ]);

            if ($streak < $rejectionThreshold) {
                return new InvoiceSendProcessResult(sent: false, error: $message, paused: false, skipReason: null);
            }

            $this->controlService->pauseSunatSendingAfterRejection(
                $taxDocumentId,
                $errorCode,
                $message,
                $streak,
                $rejectionThreshold,
                $tenantName,
            );

            return new InvoiceSendProcessResult(
                sent: false,
                error: sprintf(
                    'SUNAT sending paused after %d consecutive rejections (last code: %s).',
                    $streak,
                    $errorCode ?? 'no-code',
                ),
                paused: true,
                skipReason: null,
            );
        } catch (Throwable $exception) {
            if ($this->recoveryService->tryRecoverByConsult($taxDocumentId, $tenantName)) {
                $this->controlService->resetRejectionStreak($tenantName);

                return new InvoiceSendProcessResult(sent: true, error: null, paused: false, skipReason: null);
            }

            $this->logger->error('Error sending invoice', [
                'tax_document_id' => $taxDocumentId,
                'exception' => $exception,
            ]);

            return new InvoiceSendProcessResult(
                sent: false,
                error: $exception->getMessage(),
                paused: false,
                skipReason: null,
            );
        }
    }
}
