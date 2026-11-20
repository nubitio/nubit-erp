<?php

declare(strict_types=1);

namespace App\ControlPlane\Operator\Api;

use App\ControlPlane\Operator\Application\AdminUserOperatorService;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
#[Route('/api/control-plane/users')]
final class AdminUserApiController extends AbstractController
{
    public function __construct(
        private readonly AdminUserOperatorService $adminUsers,
    ) {}

    #[Route('', name: 'api_control_plane_users_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json(['users' => $this->adminUsers->listUsers()]);
    }

    #[Route('/{id}', name: 'api_control_plane_users_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->adminUsers->getUser($id);
        if ($user === null) {
            return $this->json(['error' => 'Admin user not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['user' => $user]);
    }

    #[Route('', name: 'api_control_plane_users_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $result = $this->adminUsers->createUser(
                (string) ($payload['username'] ?? ''),
                (string) ($payload['password'] ?? ''),
                (string) ($payload['confirmPassword'] ?? $payload['confirm_password'] ?? ''),
                $this->readRoles($payload['roles'] ?? null),
                $this->actor(),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true, 'id' => $result['id']], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_control_plane_users_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $this->adminUsers->updateUser(
                $id,
                $this->readRoles($payload['roles'] ?? null),
                (bool) ($payload['isActive'] ?? $payload['is_active'] ?? true),
                $this->actor(),
                $this->getUser()?->getUserIdentifier() ?? '',
            );
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/{id}/toggle', name: 'api_control_plane_users_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(int $id): JsonResponse
    {
        try {
            $result = $this->adminUsers->toggleUser($id, $this->actor(), $this->getUser()?->getUserIdentifier() ?? '');
        } catch (InvalidArgumentException $exception) {
            $status = str_contains($exception->getMessage(), 'not found')
                ? Response::HTTP_NOT_FOUND
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return $this->json(['error' => $exception->getMessage()], $status);
        }

        return $this->json(['ok' => true, 'is_active' => $result['is_active']]);
    }

    #[Route(
        '/{id}/reset-password',
        name: 'api_control_plane_users_reset_password',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function resetPassword(int $id, Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $this->adminUsers->resetPassword(
                $id,
                (string) ($payload['password'] ?? ''),
                (string) ($payload['confirmPassword'] ?? $payload['confirm_password'] ?? ''),
                $this->actor(),
            );
        } catch (InvalidArgumentException $exception) {
            $status = str_contains($exception->getMessage(), 'not found')
                ? Response::HTTP_NOT_FOUND
                : Response::HTTP_BAD_REQUEST;

            return $this->json(['error' => $exception->getMessage()], $status);
        }

        return $this->json(['ok' => true]);
    }

    /** @return list<string> */
    private function readRoles(mixed $roles): array
    {
        return is_array($roles) ? array_values(array_filter($roles, is_string(...))) : [];
    }

    private function actor(): string
    {
        return $this->getUser()?->getUserIdentifier() ?? 'control-plane-api';
    }
}
