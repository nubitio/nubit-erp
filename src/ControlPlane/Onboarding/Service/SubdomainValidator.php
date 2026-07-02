<?php

declare(strict_types=1);

namespace App\ControlPlane\Onboarding\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;

final readonly class SubdomainValidator
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
    ) {}

    /**
     * @return array{available:bool,reason:string,normalized:string,domain:string}
     */
    public function check(string $subdomain): array
    {
        $normalized = mb_strtolower(trim($subdomain));
        $domain = $this->buildDomain($normalized);

        if (!$this->isValidFormat($normalized)) {
            return [
                'available' => false,
                'reason' => 'Formato invalido (3-63 chars, a-z, 0-9 y guion).',
                'normalized' => $normalized,
                'domain' => $domain,
            ];
        }

        if ($this->isReserved($normalized)) {
            return [
                'available' => false,
                'reason' => 'Subdominio reservado.',
                'normalized' => $normalized,
                'domain' => $domain,
            ];
        }

        if ($this->tenantExists($normalized) || $this->domainExists($domain)) {
            return [
                'available' => false,
                'reason' => 'Subdominio no disponible.',
                'normalized' => $normalized,
                'domain' => $domain,
            ];
        }

        return [
            'available' => true,
            'reason' => '',
            'normalized' => $normalized,
            'domain' => $domain,
        ];
    }

    public function slugifyTenantName(string $tenantName): string
    {
        $slug = mb_strtolower(trim($tenantName));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if (mb_strlen($slug) < 3) {
            $slug .= '-app';
        }

        return mb_substr($slug, 0, 63);
    }

    private function isValidFormat(string $subdomain): bool
    {
        if (mb_strlen($subdomain) < 3 || mb_strlen($subdomain) > 63) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])$/', $subdomain);
    }

    private function isReserved(string $subdomain): bool
    {
        $reserved = $this->cp()->fetchOne(
            'SELECT 1 FROM reserved_subdomains WHERE name = ? LIMIT 1',
            [$subdomain],
            [ParameterType::STRING],
        );

        return $reserved !== false;
    }

    private function tenantExists(string $tenantName): bool
    {
        $exists = $this->cp()->fetchOne(
            'SELECT 1 FROM tenants WHERE name = ? LIMIT 1',
            [$tenantName],
            [ParameterType::STRING],
        );

        return $exists !== false;
    }

    private function domainExists(string $domain): bool
    {
        $exists = $this->cp()->fetchOne(
            'SELECT 1 FROM tenant_domains WHERE domain = ? LIMIT 1',
            [$domain],
            [ParameterType::STRING],
        );

        return $exists !== false;
    }

    private function buildDomain(string $subdomain): string
    {
        $root = trim(
            (string) (
                $_ENV['ONBOARDING_DOMAIN'] ?? $_SERVER['ONBOARDING_DOMAIN'] ?? getenv('ONBOARDING_DOMAIN')
                ?: 'localhost'
            ),
        );

        if ($root === '') {
            $root = 'localhost';
        }

        return $subdomain . '.' . $root;
    }

    private function cp(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->connectionRegistry->getConnection('control_plane');

        return $connection;
    }
}
