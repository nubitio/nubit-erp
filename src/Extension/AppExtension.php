<?php

namespace App\Extension;

use App\Service\OptionBuilder;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    protected OptionBuilder $optionBuilder;
    protected SessionInterface $session;

    public function __construct(OptionBuilder $optionBuilder, SessionInterface $session)
    {
        $this->optionBuilder = $optionBuilder;
        $this->session = $session;
    }

    public function getGlobals(): array
    {
        return [
            'app'    => [
                'user'    => [],
                'session' => $this->session,
                'env'     => $_ENV,
            ],
            'option' => $this->optionBuilder->buildOptions(),
        ];
    }
}