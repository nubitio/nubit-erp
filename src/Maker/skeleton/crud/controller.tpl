<?php

namespace App\{{module}}\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class {{module}}Controller extends BaseController
{
    #[Route('/{{kebabModule}}', name: '{{kebabModule}}_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('{{kebabModule}}/index.html.twig');
    }
}
