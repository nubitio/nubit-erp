<?php

declare(strict_types=1);

namespace App\Efact\Twig;

use App\Efact\Service\SettingsService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;

readonly class SettingsExtension
{
    public function __construct(
        private SettingsService $settingsService,
        #[Autowire('%env(default::APP_VERSION)%')]
        private string $appVersion = '',
    ) {}

    #[AsTwigFunction('setting')]
    public function getSetting(string $settingName): mixed
    {
        if ($settingName === 'version') {
            // The prod/staging image sets APP_VERSION from the git tag; unset
            // (local dev) means "dev", which the UI treats as "hide the badge".
            return $this->appVersion !== '' ? $this->appVersion : 'dev';
        }

        return $this->settingsService->get($settingName);
    }
}
