<?php

namespace App\Employee\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EmployeeController extends BaseController
{
    #[Route('/employee', name: 'employee_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('employee/index.html.twig');
    }
}
