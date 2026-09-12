<?php

declare(strict_types=1);

namespace App\Integration\Api;

use Symfony\Component\Routing\RouteCollection;

final class RouteContractCoverage
{
    /**
     * @param array<array-key, mixed> $contract
     * @return list<string>
     */
    public function validate(array $contract, RouteCollection $routes): array
    {
        $documented = $this->operationsFromContract($contract);
        $implemented = $this->operationsFromRoutes($routes);
        sort($documented);
        sort($implemented);
        if ($documented === $implemented) {
            return [];
        }

        return [
            'Documented and implemented /api/v1 routes differ.'
                . ' Missing from contract: '
                . implode(', ', array_diff($implemented, $documented))
                . '.'
                . ' Implemented nowhere: '
                . implode(', ', array_diff($documented, $implemented))
                . '.',
        ];
    }

    /**
     * @param array<array-key, mixed> $contract
     * @return list<string>
     */
    private function operationsFromContract(array $contract): array
    {
        $operations = [];
        $paths = is_array($contract['paths'] ?? null) ? $contract['paths'] : [];
        foreach ($paths as $path => $item) {
            if (!is_string($path) || !is_array($item)) {
                continue;
            }
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (!is_array($item[$method] ?? null)) {
                    continue;
                }
                $operations[] = strtoupper($method) . ' ' . $path;
            }
        }

        return array_values(array_unique($operations));
    }

    /** @return list<string> */
    private function operationsFromRoutes(RouteCollection $routes): array
    {
        $operations = [];
        foreach ($routes->all() as $route) {
            $path = $route->getPath();
            if ($path !== '/api/v1' && !str_starts_with($path, '/api/v1/')) {
                continue;
            }
            foreach ($route->getMethods() as $method) {
                if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], strict: true)) {
                    continue;
                }
                $operations[] = $method . ' ' . $path;
            }
        }

        return array_values(array_unique($operations));
    }
}
