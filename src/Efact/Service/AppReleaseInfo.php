<?php

declare(strict_types=1);

namespace App\Efact\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use UnexpectedValueException;

final readonly class AppReleaseInfo
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        #[Autowire('%env(default::APP_VERSION)%')]
        private string $version = '',
    ) {}

    /** @return array{version: string, revision: ?string, offlineContractVersion: int, minimumOfflineContractVersion: int} */
    public function get(): array
    {
        $policy = $this->readJson($this->projectDir . '/config/app-update.json');
        $offlineContractVersion = $policy['offlineContractVersion'] ?? null;
        $minimumOfflineContractVersion = $policy['minimumOfflineContractVersion'] ?? null;
        if (!is_int($offlineContractVersion) || !is_int($minimumOfflineContractVersion)) {
            throw new UnexpectedValueException('App update policy must contain integer contract versions.');
        }
        $path = $this->projectDir . '/public/build/entrypoints.json';
        $entries = is_file($path) ? $this->readJson($path) : [];
        $entryPoints = $entries['entryPoints'] ?? [];
        $app = is_array($entryPoints) ? $entryPoints['app'] ?? [] : [];
        $scripts = is_array($app) ? $app['js'] ?? [] : [];
        $revision = ($entries['isProd'] ?? false) === true && is_array($scripts) ? $scripts[0] ?? null : null;

        return [
            'version' => $this->version !== '' ? $this->version : 'dev',
            'revision' => is_string($revision) ? basename($revision) : null,
            'offlineContractVersion' => $offlineContractVersion,
            'minimumOfflineContractVersion' => $minimumOfflineContractVersion,
        ];
    }

    /** @return array<array-key, mixed> */
    private function readJson(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new UnexpectedValueException('Cannot read release metadata: ' . $path);
        }
        $data = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new UnexpectedValueException('Release metadata must be a JSON object: ' . $path);
        }
        return $data;
    }
}
