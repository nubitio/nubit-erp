<?php

declare(strict_types=1);

namespace App\Efact\Contract;

interface LogoProviderInterface
{
    public function __invoke(): string;

    public function embedded(): string;
}
