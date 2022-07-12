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
            'app'    => [
                'user'    => [],
                'session' => $_SESSION,
                'env'     => $_ENV,
            ],
            'option' => $this->optionBuilder->buildOptions(),
        ];
    }
}