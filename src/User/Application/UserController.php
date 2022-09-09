<?php

namespace App\User\Application;

use App\Base\BaseController;
use App\User\Domain\UserGetAllUseCase;
use App\User\Domain\UserCreateUseCase;
use App\User\Domain\UserUpdateUseCase;
use App\User\Domain\UserDeleteUseCase;
use App\User\Domain\User;
use App\Util\Constants;
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
    public function getAll(): Response
    {
        $getAllUseCase = $this->container->get(UserGetAllUseCase::class);
        return $this->json($getAllUseCase());
    }

    #[Route('/user/add', name: 'user_create', methods: ['POST'])]
    public function add(Request $request): Response
    {
        /**
         * @var User $user
         */
        $user = $this->serializer->deserialize(json_encode($request->request->all()), User::class, 'json');
        $createUseCase = $this->container->get(UserCreateUseCase::class);
        $createUseCase($user);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/user/edit/{id}', name: 'user_update', methods: ['POST'])]
    public function edit(Request $request, $id): Response
    {
        $request->request->add(['usuario_id' => $id]);
        /**
         * @var User $user
         */
        $user = $this->serializer->deserialize(json_encode($request->request->all()), User::class, 'json');
        $updateUseCase = $this->container->get(UserUpdateUseCase::class);
        $updateUseCase($user);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/user/delete/{id}', name: 'user_delete', methods: ['POST'])]
    public function delete($id): Response
    {
        $deleteUseCase = $this->container->get(UserDeleteUseCase::class);
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
