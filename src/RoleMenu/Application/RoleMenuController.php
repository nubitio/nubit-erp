<?php

namespace App\RoleMenu\Application;

use App\Base\BaseController;
use App\RoleMenu\Domain\RoleMenu;
use App\RoleMenu\Domain\RoleMenuCreateUseCase;
use App\RoleMenu\Domain\RoleMenuGetAllByRoleUseCase;
use App\Core\Domain\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RoleMenuController extends BaseController
{
    #[Route('/role-menu/get', name: 'role-menu_list', methods: ['GET'])]
    public function getAllByRole(Request $request, RoleMenuGetAllByRoleUseCase $getAllUseCase): Response
    {
        $roleId = $request->query->getInt('roleId');

        return $this->json($getAllUseCase($roleId));
    }

    #[Route('/role-menu/add', name: 'role-menu_create', methods: ['POST'])]
    public function add(Request $request, RoleMenuCreateUseCase $createUseCase): Response
    {
        $data = json_decode($request->getContent(), true);
        $roleMenuList = [];
        foreach ($data['permissions'] as $menu) {
            $roleMenu = new RoleMenu(
                menu_id: $menu,
                rol_id: $data['roleId'],
                estado: true
            );
            $roleMenuList[] = $roleMenu;
        }
        $createUseCase($data['roleId'], $roleMenuList);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }
}
