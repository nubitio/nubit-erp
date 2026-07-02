<?php

declare(strict_types=1);

namespace App\Efact\Twig;

use App\Efact\Service\SettingsService;
use Twig\Attribute\AsTwigFunction;

readonly class SettingsExtension
{
    public function __construct(
        private SettingsService $settingsService,
    ) {}

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
