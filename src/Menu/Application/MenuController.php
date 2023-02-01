<?php

namespace App\Menu\Application;

use App\Base\BaseController;
use App\Core\Infrastructure\Service\MenuBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class MenuController extends BaseController
{
    #[Route('/menu/{roleId}', name: 'menu', methods: ['GET'])]
    public function getMenuByRole(int $roleId, MenuBuilder $menuBuilder): JsonResponse
    {
        $menu = $menuBuilder->buildMenuForRole($roleId);
        return $this->json($menu);
    }
}