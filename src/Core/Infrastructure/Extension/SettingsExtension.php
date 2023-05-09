<?php

namespace App\Core\Infrastructure\Extension;

use App\Core\Infrastructure\Service\SettingsService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SettingsExtension extends AbstractExtension
{
    public function __construct(
        private readonly SettingsService $settingsService
    )
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting', [$this, 'getSetting']),
        ];
    }

    public function getSetting(string $settingName): mixed
    {
        $version = 'dev';

        if ($settingName === 'version') {
            $versionFile = dirname(__DIR__, 4) . '/.version';
            if (file_exists($versionFile)) {
                $fileContent = file_get_contents($versionFile);
                $version = trim(strval($fileContent ?: $version));
            }
        }

        return match ($settingName) {
            'version' => $version,
            default => $this->settingsService->get($settingName),
        };
    }
}
