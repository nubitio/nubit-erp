<?php

declare(strict_types=1);

namespace App\Efact\Observability;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Nubit\Platform\Tenant\Context\TenantContext;

/**
 * NB-20 (#346), gap G9: stamp every structured log record with the identity of
 * the operation it belongs to, so a single business action can be followed
 * across bounded contexts and across the async worker boundary.
 *
 * `Nubit\Platform\Observability\Logging\TenantLogProcessor` already adds
 * `tenant_id`, `request_id`, `trace_id` and `span_id`. This processor adds the
 * two fields NB-20 additionally names:
 *
 *  - `user_id`         — the acting principal (username / API key id / CLI
 *                        command), from TenantContext, restored in the worker
 *                        from the message's ActorStamp.
 *  - `correlation_id`  — a stable alias of `request_id`. One value for the whole
 *                        fan-out of an operation (a credit sale that triggers
 *                        stock, CPE and caja shares it), propagated between
 *                        contexts by the Messenger TenantStamp.
 */
#[AsMonologProcessor]
final readonly class OperationContextProcessor
{
    public function __construct(
        private TenantContext $tenantContext,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $actor = $this->tenantContext->getActorIdentifier();
        $correlationId = $this->tenantContext->getRequestId();

        $context = $record->context;
        $context['user_id'] = $actor !== null && trim($actor) !== '' ? trim($actor) : null;
        $context['correlation_id'] = $correlationId;

        return $record->with(context: $context);
    }
}
