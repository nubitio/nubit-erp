<?php

namespace App\Role\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RoleController extends BaseController
{
    #[Route('/role', name: 'role_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('role/index.html.twig');
    }
}
