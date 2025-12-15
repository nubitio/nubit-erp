<?php

declare(strict_types=1);

namespace App\Core\Extension;

use Twig\Attribute\AsTwigFunction;
use App\Core\Service\LogoProvider;
use Doctrine\DBAL\Exception;

readonly class LogoExtension
{
    public function __construct(
        private LogoProvider $logoProvider,
    ) {
    }

    /**
     * @throws Exception
     */
    #[AsTwigFunction('logo')]
    public function getLogo(): string
    {
        return $this->logoProvider->__invoke();
    }
}
