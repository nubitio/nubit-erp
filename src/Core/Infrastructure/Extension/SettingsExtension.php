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

    public function getSetting(string $settingName): string
    {
        $version = 'dev';
        $maintenance = 'false';

        if ($settingName === 'version') {
            $versionFile = dirname(__DIR__, 4) . '/.version';
            $version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'dev';
        }

        return match ($settingName) {
            'version' => $version,
            default => $this->settingsService->get($settingName),
        };
    }
}
