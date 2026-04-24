<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Adapter;

use App\ControlPlane\Tenant\Port\ElectronicBillingCompanyPortInterface;
use App\EInvoicing\Billing\Domain\LycetCompanyServiceInterface;

final readonly class LycetElectronicBillingCompanyAdapter implements ElectronicBillingCompanyPortInterface
{
    public function __construct(
        private LycetCompanyServiceInterface $lycetCompanyService,
    ) {
    }

    /**
     * @param array<string, string> $urls
     */
    public function upsertCompany(
        string $ruc,
        string $solUser,
        string $solPass,
        string $certPem,
        array $urls = [],
        ?string $clientId = null,
        ?string $clientSecret = null,
    ): void {
        $this->lycetCompanyService->upsertCompany($ruc, $solUser, $solPass, $certPem, $urls, $clientId, $clientSecret);
    }

    public function uploadCompanyLogo(string $ruc, string $imageData): void
    {
        $this->lycetCompanyService->uploadCompanyLogo($ruc, $imageData);
    }

    public function removeCompany(string $ruc): void
    {
        $this->lycetCompanyService->removeCompany($ruc);
    }
}
