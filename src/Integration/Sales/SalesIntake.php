<?php

declare(strict_types=1);

namespace App\Integration\Sales;

use App\Integration\Security\ApiClientUser;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class SalesIntake
{
    public function __construct(
        private Connection $connection,
        private SalesClientAccess $access,
    ) {}

    /**
     * The adapter supplies a SHA-256 hash of the canonical command, not the raw JSON.
     * The callback must only write local effects on this tenant connection and return its sale ID.
     * A failure rolls back the claim as well as the sale. Do not send CPE or call external providers here.
     *
     * @param \Closure(string, string): string $confirm receives client ID and the authorized branch ID
     * @return array{sale_id: string, replayed: bool}
     */
    public function submit(
        ApiClientUser $client,
        string $requestedBranch,
        string $key,
        string $payloadHash,
        \Closure $confirm,
    ): array {
        $branchId = $this->access->requireBranch($client, $requestedBranch);
        if (
            preg_match('/\A[A-Za-z0-9._:-]{1,128}\z/', $key) !== 1
            || preg_match('/\A[a-f0-9]{64}\z/', $payloadHash) !== 1
        ) {
            throw new UnprocessableEntityHttpException('Invalid sales idempotency key or command hash.');
        }

        $clientId = $client->getClientId();

        return $this->connection->transactional(static function (Connection $connection) use (
            $clientId,
            $key,
            $branchId,
            $payloadHash,
            $confirm,
        ): array {
            // The unique key and the upsert serialize contenders before either can touch stock or sales.
            $connection->executeStatement(
                <<<'SQL'
                    INSERT INTO integration_sales_request (client_id, idempotency_key, branch_id, payload_hash)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE idempotency_key = idempotency_key
                    SQL,
                [$clientId, $key, $branchId, $payloadHash],
            );
            $existing = $connection->fetchAssociative(
                'SELECT branch_id, payload_hash, sale_id FROM integration_sales_request WHERE client_id = ? AND idempotency_key = ? FOR UPDATE',
                [$clientId, $key],
            );
            if ($existing === false) {
                throw new \LogicException('The sales request reservation was lost.');
            }
            if ($existing['branch_id'] !== $branchId || $existing['payload_hash'] !== $payloadHash) {
                throw new ConflictHttpException('This idempotency key already belongs to a different sales command.');
            }
            if (is_string($existing['sale_id'])) {
                return ['sale_id' => $existing['sale_id'], 'replayed' => true];
            }

            $saleId = $confirm($clientId, $branchId);
            $linked = $connection->executeStatement(
                'UPDATE integration_sales_request SET sale_id = ? WHERE client_id = ? AND idempotency_key = ? AND EXISTS
                    (SELECT 1 FROM sales WHERE id = ? AND integration_client_id = ? AND branch_id = ?)',
                [$saleId, $clientId, $key, $saleId, $clientId, $branchId],
            );
            if ((int) $linked !== 1) {
                throw new \LogicException('The confirmed sale does not belong to the authorized client and branch.');
            }

            return ['sale_id' => $saleId, 'replayed' => false];
        });
    }
}
