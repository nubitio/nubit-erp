<?php

namespace App\Core\Extension;

use App\Core\Service\LogoProvider;
use Doctrine\DBAL\Exception;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LogoExtension extends AbstractExtension
{
    public function __construct(
        private readonly LogoProvider $logoProvider,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('logo', $this->getLogo(...)),
        ];
    }

    /**
     * @throws Exception
     */
    public function getLogo(): string
    {
        return $this->logoProvider->__invoke();
    }
}
