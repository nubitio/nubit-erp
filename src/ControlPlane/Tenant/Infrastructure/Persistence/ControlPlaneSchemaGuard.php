<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use RuntimeException;

final readonly class ControlPlaneSchemaGuard
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private LoggerInterface $logger,
    ) {}

    public function connection(): Connection
    {
        return $this->connectionRegistry->getConnection('control_plane');
    }

    public function isReady(?Connection $connection = null): bool
    {
        $connection ??= $this->connection();

        try {
            return $connection->createSchemaManager()->tablesExist(['tenants']);
        } catch (DbalException $exception) {
            $this->logger->warning('Failed to verify control plane schema', [
                'exception' => $exception,
            ]);

            return false;
        }
    }

    public function requireReady(string $operation): void
    {
        if ($this->isReady()) {
            return;
        }

        throw new RuntimeException(sprintf('Control plane database not ready while attempting to %s.', $operation));
    }
}
