<?php

namespace App\SandBox;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SandBoxController extends BaseController
{
    #[Route('/sandbox', name: 'sandbox', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('sandbox/index.html.twig', [
            'content' => 'Sandbox',
        ]);
    }
}
