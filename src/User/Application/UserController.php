<?php

namespace App\User\Application;

use App\Base\BaseController;
use App\User\Domain\ChangePasswordUseCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends BaseController
{
    #[Route('/user', name: 'user_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('user/index.html.twig');
    }

    #[Route('/changePassword/update-password', name: 'update_password', methods: ['POST'])]
    public function changePassword(Request $request, ChangePasswordUseCase $changePasswordUseCase): Response
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['userId'];
        $data = json_decode($request->getContent(), true);
        $actualPassword = $data['actualPassword'];
        $data = json_decode($request->getContent(), true);
        $newPassword = $data['newPassword'];

        $changePasswordUseCase($userId, $actualPassword, $newPassword);

        return $this->json(['success'=> true, 'msg' => 'Contraseña Actualizada']);
    }
}
