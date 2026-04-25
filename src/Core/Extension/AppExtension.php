<?php

declare(strict_types=1);

namespace App\Core\Extension;

use App\Core\Security\CspNonceService;
use App\Efact\Service\LogoProvider;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

readonly class AppExtension
{
    public function __construct(
        private LogoProvider $logoProvider,
        private RequestStack $requestStack,
        private CspNonceService $cspNonceService,
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

    /**
     * Returns the CSP nonce for the current request.
     * Use it as the nonce attribute on inline <script> and <style> tags:
     *   <script nonce="{{ csp_nonce() }}">…</script>
     */
    #[AsTwigFunction('csp_nonce')]
    public function getCspNonce(): string
    {
        return $this->cspNonceService->getNonce();
    }
}
