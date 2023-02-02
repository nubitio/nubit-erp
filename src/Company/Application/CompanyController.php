<?php

namespace App\Company\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CompanyController extends BaseController
{
    #[Route('/company', name: 'company_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('company/index.html.twig');
    }
}
