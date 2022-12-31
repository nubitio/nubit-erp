<?php

namespace App\User\Application;

use App\Base\BaseController;
use App\User\Domain\User;
use App\User\Domain\UserCreateUseCase;
use App\User\Domain\UserDeleteUseCase;
use App\User\Domain\UserGetAllUseCase;
use App\User\Domain\UserUpdateUseCase;
use App\Core\Domain\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Route('/user', name: 'user_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('user/index.html.twig');
    }

    #[Route('/user/get', name: 'user_list', methods: ['POST'])]
    public function getAll(UserGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/user/add', name: 'user_create', methods: ['POST'])]
    public function add(Request $request, UserCreateUseCase $createUseCase): Response
    {
        /**
         * @var User $user
         */
        $user = $this->serializer->deserialize(json_encode($request->request->all()), User::class, 'json');
        $createUseCase($user);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/user/edit/{id}', name: 'user_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, UserUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['usuario_id' => $id]);
        /**
         * @var User $user
         */
        $user = $this->serializer->deserialize(json_encode($request->request->all()), User::class, 'json');
        $updateUseCase($user);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/user/delete/{id}', name: 'user_delete', methods: ['DELETE', 'POST'])]
    public function delete(UserDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
