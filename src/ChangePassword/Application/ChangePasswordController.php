<?php

namespace App\ChangePassword\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ChangePasswordController extends BaseController
{
    #[Route('change-password', name: 'change_password', methods: ['GET'])]
    public function changePassword(): Response
    {
        return $this->render('change-password/index.html.twig');
    }
}
