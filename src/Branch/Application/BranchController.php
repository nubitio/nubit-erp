<?php

namespace App\Branch\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BranchController extends BaseController
{
    #[Route('/branch', name: 'branch_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('branch/index.html.twig');
    }
}
