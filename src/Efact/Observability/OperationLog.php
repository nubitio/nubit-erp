<?php

declare(strict_types=1);

namespace App\Efact\Observability;

use Psr\Log\LoggerInterface;
use Stringable;

use function sprintf;

/**
 * NB-20 (#346): a normalised structured-log entry for one business operation.
 *
 * `record()` emits a single INFO line on the dedicated `operation` channel with
 * `module`, `operation` and `entity_id`. `OperationContextProcessor` and the
 * platform `TenantLogProcessor` then decorate it with `user_id`,
 * `correlation_id`, `tenant_id`, `trace_id` — so every step of a fan-out
 * (sale → stock → CPE → caja) is queryable by one `correlation_id`.
 *
 * This is the operational log stream, not the audit trail: use
 * {@see \App\Efact\Audit\AuditLogger} when the event must be persisted for
 * compliance.
 */
final readonly class OperationLog
{
    public function __construct(
        private LoggerInterface $operationLogger,
    ) {}

    /**
     * @param string          $module    owning bounded context, lower-case (e.g. "sales", "inventory", "billing")
     * @param string          $operation dotted verb phrase (e.g. "sale.created", "receivable.collected")
     * @param string|int|null $entityId  primary key of the entity the operation acted on
     * @param array<string, scalar|Stringable|null> $context extra structured fields
     */
    public function record(
        string $module,
        string $operation,
        string|int|null $entityId = null,
        array $context = [],
    ): void {
        $this->operationLogger->info(sprintf('%s %s', $module, $operation), [
            'module' => $module,
            'operation' => $operation,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            ...$context,
        ]);
    }
}
