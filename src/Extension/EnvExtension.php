<?php

namespace App\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class EnvExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('env', [$this, 'getEnv']),
        ];
    }

    public function getEnv(string $name): string
    {
        return $_ENV[$name];
    }
}