<?php

namespace App\Extension;

use App\Service\SettingsService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    protected SettingsService $settings;
    protected SessionInterface $session;

    public function __construct(SettingsService $settings, SessionInterface $session)
    {
        $this->settings = $settings;
        $this->session = $session;
    }

    public function getGlobals(): array
    {
        return [
            'app'     => [
                'user'    => [],
                'session' => $this->session,
                'env'     => $_ENV,
            ],
            'settings' => $this->settings,
        ];
    }
}