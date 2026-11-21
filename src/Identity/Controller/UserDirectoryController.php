<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Directory\GridQueryParser;
use App\Identity\Directory\UserDirectory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The people of the tenant for the users grid: users and pending invitations in one paged list.
 * Reading only; the rows are created, edited and deleted through `/api/users` and the invitation
 * endpoints ({@see PendingInvitationController}).
 */
final class UserDirectoryController extends AbstractController
{
    public function __construct(
        private readonly UserDirectory $directory,
    ) {}

    #[Route('/api/user-directory', name: 'api_user_directory', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('perm:users.view');

        // `member` / `totalItems` is the collection shape the datagrid's Hydra adapter reads.
        return $this->json($this->directory->page(GridQueryParser::parse($request->query->all())));
    }
}
