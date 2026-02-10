<?php

declare(strict_types=1);

namespace App\Core\Context;

final class TenantContext
{
    private ?int $tenantId = null;
    private ?string $tenantName = null;
    private ?string $tenantDomain = null;
    private ?string $requestId = null;

    public function setTenant(
        ?int $tenantId,
        ?string $tenantName,
        ?string $tenantDomain,
        ?string $requestId,
    ): void {
        $this->tenantId = $tenantId;
        $this->tenantName = $tenantName;
        $this->tenantDomain = $tenantDomain;
        $this->requestId = $requestId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->tenantName = null;
        $this->tenantDomain = null;
        $this->requestId = null;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function getTenantName(): ?string
    {
        return $this->tenantName;
    }

    public function getTenantDomain(): ?string
    {
        return $this->tenantDomain;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
