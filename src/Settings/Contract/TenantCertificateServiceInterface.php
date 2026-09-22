<?php

declare(strict_types=1);

namespace App\Settings\Contract;

use App\Settings\Dto\TenantCertificateView;

interface TenantCertificateServiceInterface
{
    public function upsert(
        string $tenantName,
        string $ruc,
        string $solUser,
        string $solPass,
        string $certPem,
        ?string $uploadedBy = null,
        ?callable $beforeCommit = null,
    ): TenantCertificateView;

    /** @return list<TenantCertificateView> */
    public function list(string $tenantName): array;
}
