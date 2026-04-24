<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Port;

interface ElectronicBillingCompanyPortInterface
{
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
    ): void;

    public function uploadCompanyLogo(string $ruc, string $imageData): void;

    public function removeCompany(string $ruc): void;
}
