<?php

namespace App\SandBox;

use App\Base\BaseController;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\SettingsService;
use App\Core\Infrastructure\Service\TenantAwareSettingsService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SandBoxController extends BaseController
{
    #[Route('/sandbox', name: 'sandbox', methods: ['GET'])]
    public function index(TenantAwareSettingsService $tenantAwareSettingsService, SettingsService $settingsService): Response
    {
        $companyNameByTenant = $tenantAwareSettingsService->get(Constants::COMPANY_NAME, 'No hay datos');
        $companyName = $settingsService->get(Constants::COMPANY_NAME, 'No hay datos');

        $content = 'Company name by tenant: '.$companyNameByTenant.' <br> Company name: '.$companyName;

        return $this->render('sandbox/index.html.twig', [
            'content' => $content,
        ]);
    }

    #[Route('/spa', name: 'sandbox_spa', methods: ['GET'])]
    public function spa(): Response
    {
        return $this->render('layout/index.html.twig');
    }
}
