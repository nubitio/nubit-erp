<?php

declare(strict_types=1);

namespace App\Efact\Web;

use App\Efact\Contract\LogoProviderInterface;
use Nubit\ApiPlatform\Http\BaseController;
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
    public function logo(LogoProviderInterface $logoProvider): Response
    {
        $logoUrl = $logoProvider();

        return $this->redirect($logoUrl);
    }
}
