<?php

namespace App\Config\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ConfigController extends BaseController
{
    #[Route('/config', name: 'config_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/index.html.twig');
    }
}
