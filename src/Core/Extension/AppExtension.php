<?php

declare(strict_types=1);

namespace App\Core\Extension;

use Twig\Attribute\AsTwigFunction;
use App\Core\Service\LogoProvider;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class AppExtension
{
    public function __construct(
        private LogoProvider $logoProvider,
        private RequestStack $requestStack,
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

    #[AsTwigFunction('base_url')]
    public function getBaseUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->getSchemeAndHttpHost() ?? '';
    }
}
