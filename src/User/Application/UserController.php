<?php

namespace App\User\Application;

use App\Controller\BaseController;
use App\User\Domain\UserGetAllUseCase;
use App\User\Domain\UserCreateUseCase;
use App\User\Domain\UserUpdateUseCase;
use App\User\Domain\UserDeleteUseCase;
use App\User\Domain\User;
use App\Util\Constants;

class UserController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->render('user/index.html.twig');
    }

    public function getAll()
    {
        $getAllUseCase = $this->container->get(UserGetAllUseCase::class);
        $this->json($getAllUseCase());
    }

    public function add()
    {
        /**
         * @var User $entity
         */
        $entity = $this->serializer->deserialize(json_encode($_POST), User::class, 'json');
        $createUseCase = $this->container->get(UserCreateUseCase::class);
        $createUseCase($entity);
        $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    public function edit()
    {
        /**
         * @var User $entity
         */
        $entity = $this->serializer->deserialize(json_encode($_POST), User::class, 'json');
        $updateUseCase = $this->container->get(UserUpdateUseCase::class);
        $updateUseCase($entity);
        $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    public function delete()
    {
        $id = $this->app->get('PARAMS.id');
        $deleteUseCase = $this->container->get(UserDeleteUseCase::class);
        $deleteUseCase($id);
        $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
