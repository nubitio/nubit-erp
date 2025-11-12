<?php

namespace App\Controller;

use App\Core\Base\BaseController;
use App\Core\Service\LogoProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends BaseController
{
    #[Route('/', name: 'home_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/logo', name: 'logo', methods: ['GET'])]
    public function logo(LogoProvider $logoProvider): Response
    {
        $logoUrl = $logoProvider();

        return $this->redirect($logoUrl);
    }
}
