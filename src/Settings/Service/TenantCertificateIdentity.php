<?php

declare(strict_types=1);

namespace App\Settings\Service;

use App\Organization\Repository\CompanyRepository;

final readonly class TenantCertificateIdentity
{
    public function __construct(
        private CompanyRepository $companies,
    ) {}

    public function ruc(mixed $requested): string
    {
        $company = $this->companies->findOneBy([]);
        if ($company === null) {
            throw new \InvalidArgumentException('No se encontro la identidad fiscal del tenant.');
        }
        $tenantRuc = $company->getRuc();
        if ($requested !== null && (!is_string($requested) || preg_match('/^\d{11}$/', $requested) !== 1)) {
            throw new \InvalidArgumentException('El RUC debe tener 11 digitos.');
        }
        if (is_string($requested) && !hash_equals($tenantRuc, $requested)) {
            throw new \InvalidArgumentException('El RUC no pertenece al tenant autenticado.');
        }
        return is_string($requested) ? $requested : $tenantRuc;
    }

    /** @param array<string, mixed> $payload */
    public function requiredString(array $payload, string $key): string
    {
        /** @var mixed $value */
        $value = $payload[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException(sprintf('Debe indicar "%s".', $key));
        }
        return trim($value);
    }

    public function solUserWithRuc(string $solUser, string $ruc): string
    {
        return (
            $ruc
            . (
                preg_replace('/^' . preg_quote($ruc, delimiter: '/') . '/', replacement: '', subject: $solUser)
                ?? $solUser
            )
        );
    }
}
