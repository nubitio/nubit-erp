<?php

namespace App\Role\Application;

use App\Base\BaseController;
use App\Role\Domain\Role;
use App\Role\Domain\RoleCreateUseCase;
use App\Role\Domain\RoleDeleteUseCase;
use App\Role\Domain\RoleGetAllUseCase;
use App\Role\Domain\RoleUpdateUseCase;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RoleController extends BaseController
{
    #[Route('/role', name: 'role_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('role/index.html.twig');
    }

    #[Route('/role/get', name: 'role_list', methods: ['POST'])]
    public function getAll(RoleGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/role/add', name: 'role_create', methods: ['POST'])]
    public function add(Request $request, RoleCreateUseCase $createUseCase): Response
    {
        /**
         * @var Role $role
         */
        $role = $this->serializer->deserialize(json_encode($request->request->all()), Role::class, 'json');
        $createUseCase($role);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/role/edit/{id}', name: 'role_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, RoleUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['rol_id' => $id]);
        /**
         * @var Role $role
         */
        $role = $this->serializer->deserialize(json_encode($request->request->all()), Role::class, 'json');
        $updateUseCase($role);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/role/delete/{id}', name: 'role_delete', methods: ['DELETE', 'POST'])]
    public function delete(RoleDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
