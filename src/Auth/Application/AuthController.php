<?php

namespace App\Auth\Application;

use App\Base\BaseController;
use App\Branch\Domain\BranchGetOneUseCase;
use App\Core\Domain\Util\ArrayUtils;
use App\User\Domain\User;
use App\Warehouse\Domain\GetAllByBranchUseCase;
use App\Warehouse\Domain\Warehouse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class AuthController extends BaseController
{
    #[Route('/login', name: 'login_index', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] ?User $user): Response
    {
        if ($request->getSession()->get('branch_id') && $user) {
            return $this->redirectToRoute('dashboard_index');
        }

        return $this->render('auth/index.html.twig', [
            'title' => 'Login',
        ]);
    }

    #[Route('/auth/login', name: 'login', methods: ['POST'])]
    public function login(): Response
    {
        throw new \Exception('This should never be reached');
    }

    #[Route('/auth/logout', name: 'logout', methods: ['GET'])]
    public function logout(): Response
    {
        throw new \Exception('This should never be reached!');
    }

    #[Route('/auth/local', name: 'auth_local', methods: ['POST'])]
    public function setLocal(Request $request, BranchGetOneUseCase $branchGetOneUseCase, GetAllByBranchUseCase $getAllByBranchUseCase): Response
    {
        /** @var array<string, string> $data */
        $data = json_decode($request->getContent(), true);
        $localId = $data['localId'];

        $local = $branchGetOneUseCase($localId);

        $warehouses = $getAllByBranchUseCase($localId);
        /** @var ?Warehouse $defaultWarehouse */
        $defaultWarehouse = ArrayUtils::find($warehouses, fn($warehouse) => $warehouse->is_default);

        $session = $request->getSession();
        $session->set('branch_id', $localId);
        $session->set('branch_name', $local?->descripcion ?? null);
        $session->set('warehouse_id', $defaultWarehouse?->almacen_id ?? null);

        return $this->json([
            'success' => true,
            'data'    => $session->all(),
        ]);
    }
}
