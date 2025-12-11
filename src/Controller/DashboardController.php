<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Auth\AuthUserDTO;
use App\Core\Application\Session\Security;
use App\Repository\DashboardRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardRepository $repository,
    ) {
    }

    public function __invoke(Request $request, Security $security): Response
    {
        $type = $request->attributes->get('type');
        $user = $security->connectedUser();

        if (!$user instanceof AuthUserDTO) {
            return $this->json([]);
        }

        $localId = $user->branchId;

        if ($type == 'overview') {
            $data = $this->repository->getOverview($localId);
        }

        return $this->json($data ?? []);
    }
}
