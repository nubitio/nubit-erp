<?php

declare(strict_types=1);

namespace App\Core\Extension;

use Twig\Attribute\AsTwigFunction;
use App\Core\Service\SettingsService;

class SettingsExtension
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {
    }

    #[AsTwigFunction('setting')]
    public function getSetting(string $settingName): mixed
    {
        $version = 'dev';

        if ('version' === $settingName) {
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
