<?php

declare(strict_types=1);

namespace App\Efact\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

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
        $policy = json_decode(
            file_get_contents($this->projectDir . '/config/app-update.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );
        $path = $this->projectDir . '/public/build/entrypoints.json';
        $entries = is_file($path)
            ? json_decode(file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR)
            : [];
        $revision = $entries['isProd'] ?? false ? $entries['entryPoints']['app']['js'][0] ?? null : null;

        return [
            'version' => $this->version !== '' ? $this->version : 'dev',
            'revision' => $revision !== null ? basename($revision) : null,
            'offlineContractVersion' => (int) $policy['offlineContractVersion'],
            'minimumOfflineContractVersion' => (int) $policy['minimumOfflineContractVersion'],
        ];
    }
}
