<?php

namespace App\Extension;

use App\Service\OptionBuilder;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    protected OptionBuilder $optionBuilder;

    public function __construct(OptionBuilder $optionBuilder)
    {
        $this->optionBuilder = $optionBuilder;
    }

    public function getGlobals(): array
    {
        return [
            'app'  => [
                'name'    => $_ENV['APP_NAME'],
                'user'    => [],
                'session' => $_SESSION,
            ],
            'option' => $this->optionBuilder->buildOptions(),
        ];
    }
}