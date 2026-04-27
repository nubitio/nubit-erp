<?php

declare(strict_types=1);

namespace App\Core\Platform\Tenant;

interface TenantBackupRunnerInterface extends \Nubit\Platform\Tenant\Contract\TenantBackupRunnerInterface
{
    /**
     * @return array{id: int, filename: string, storage_path: string, size_bytes: int, storage_type: string}
     */
    public function backup(string $tenantName, bool $uploadToS3 = true, string $backupType = 'full'): array;
}
