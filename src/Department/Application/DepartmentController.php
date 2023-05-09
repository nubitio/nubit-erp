<?php

namespace App\Department\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DepartmentController extends BaseController
{
    #[Route('/department', name: 'department_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('department/index.html.twig');
    }
}
