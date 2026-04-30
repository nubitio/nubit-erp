<?php

declare(strict_types=1);

namespace App\ControlPlane\Admin\User;

use App\Efact\Audit\AuditLogger;
use App\ControlPlane\Admin\Security\ControlPlaneUser;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
#[Route('/admin/users')]
final class AdminUserController extends AbstractController
{
    private const ALLOWED_ROLES = ['ROLE_CP_VIEWER', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'];

    public function __construct(
        private readonly AdminUserRepository $adminUserRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AuditLogger $auditLogger,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws Exception
     */
    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(): Response
    {
        $users = $this->adminUserRepository->findAll();
        $users = array_map($this->decodeUserRoles(...), $users);

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
        ]);
    }

    /**
     * @throws Exception
     */
    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_user', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid security token. Please try again.');

                return $this->redirectToRoute('admin_user_new');
            }

            $username        = strtolower(trim((string) $request->request->get('username', '')));
            $password        = (string) $request->request->get('password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');
            $roles           = $this->filterRoles((array) $request->request->all('roles'));

            $errors = $this->validateUsername($username);
            $errors = array_merge($errors, $this->validatePassword($password));

            if ($password !== $confirmPassword) {
                $errors[] = 'Passwords do not match.';
            }

            if ($errors === [] && $this->adminUserRepository->usernameExists($username)) {
                $errors[] = sprintf('Username "%s" is already taken.', $username);
            }

            if ($roles === []) {
                $errors[] = 'At least one role must be selected.';
            }

            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }

                return $this->render('admin/user/new.html.twig', [
                    'username'      => $username,
                    'selected_roles' => $roles,
                    'allowed_roles' => self::ALLOWED_ROLES,
                ]);
            }

            $passwordHash = $this->hashPassword($password);
            $newId = $this->adminUserRepository->create($username, $passwordHash, $roles);

            $actor = $this->getUser()?->getUserIdentifier() ?? 'system';
            $this->auditLogger->log('admin_user.create', [
                'actor'    => $actor,
                'new_user' => $username,
                'user_id'  => $newId,
                'roles'    => $roles,
            ]);
            $this->logger->info('Admin user created', [
                'actor'    => $actor,
                'new_user' => $username,
                'user_id'  => $newId,
            ]);

            $this->addFlash('success', sprintf('Admin user "%s" created successfully.', $username));

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/new.html.twig', [
            'username'      => '',
            'selected_roles' => ['ROLE_ADMIN'],
            'allowed_roles' => self::ALLOWED_ROLES,
        ]);
    }

    /**
     * @throws Exception
     */
    #[Route('/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->adminUserRepository->findById($id);
        if ($user === null) {
            $this->addFlash('error', 'Admin user not found.');

            return $this->redirectToRoute('admin_user_index');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_user', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid security token. Please try again.');

                return $this->redirectToRoute('admin_user_edit', ['id' => $id]);
            }

            $roles    = $this->filterRoles((array) $request->request->all('roles'));
            $isActive = $request->request->getBoolean('is_active', true);

            if ($roles === []) {
                $this->addFlash('error', 'At least one role must be selected.');

                return $this->render('admin/user/edit.html.twig', [
                    'user'          => $this->decodeUserRoles($user),
                    'allowed_roles' => self::ALLOWED_ROLES,
                ]);
            }

            $this->adminUserRepository->update($id, [
                'roles'     => $roles,
                'is_active' => $isActive,
            ]);

            $actor = $this->getUser()?->getUserIdentifier() ?? 'system';
            $this->auditLogger->log('admin_user.edit', [
                'actor'   => $actor,
                'user_id' => $id,
                'username' => (string) $user['username'],
                'roles'   => $roles,
                'is_active' => $isActive,
            ]);
            $this->logger->info('Admin user edited', [
                'actor'    => $actor,
                'user_id'  => $id,
                'username' => (string) $user['username'],
            ]);

            $this->addFlash('success', 'Admin user updated successfully.');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'user'          => $this->decodeUserRoles($user),
            'allowed_roles' => self::ALLOWED_ROLES,
        ]);
    }

    /**
     * Toggles active/inactive status.
     * Returns JSON for AJAX callers; redirects otherwise.
     *
     * @throws Exception
     */
    #[Route('/{id}/toggle', name: 'admin_user_toggle', methods: ['POST'])]
    public function toggle(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_user', (string) $request->request->get('_token'))) {
            if ($this->isAjax($request)) {
                return $this->json(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_user_index');
        }

        $user = $this->adminUserRepository->findById($id);
        if ($user === null) {
            if ($this->isAjax($request)) {
                return $this->json(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
            }
            $this->addFlash('error', 'Admin user not found.');

            return $this->redirectToRoute('admin_user_index');
        }

        $currentUser = $this->getUser();
        if ($currentUser !== null && $currentUser->getUserIdentifier() === (string) $user['username']) {
            if ($this->isAjax($request)) {
                return $this->json(['error' => 'You cannot deactivate your own account.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->addFlash('error', 'You cannot deactivate your own account.');

            return $this->redirectToRoute('admin_user_index');
        }

        $wasActive = (bool) $user['is_active'];
        if ($wasActive) {
            $this->adminUserRepository->deactivate($id);
        } else {
            $this->adminUserRepository->activate($id);
        }

        $newStatus = !$wasActive;
        $actor     = $currentUser?->getUserIdentifier() ?? 'system';

        $this->auditLogger->log('admin_user.toggle', [
            'actor'      => $actor,
            'user_id'    => $id,
            'username'   => (string) $user['username'],
            'is_active'  => $newStatus,
        ]);
        $this->logger->info('Admin user toggled', [
            'actor'     => $actor,
            'user_id'   => $id,
            'username'  => (string) $user['username'],
            'is_active' => $newStatus,
        ]);

        if ($this->isAjax($request)) {
            return $this->json([
                'status'    => 'ok',
                'is_active' => $newStatus,
                'message'   => sprintf(
                    'User "%s" %s.',
                    (string) $user['username'],
                    $newStatus ? 'activated' : 'deactivated',
                ),
            ]);
        }

        $this->addFlash('success', sprintf(
            'User "%s" %s.',
            (string) $user['username'],
            $newStatus ? 'activated' : 'deactivated',
        ));

        return $this->redirectToRoute('admin_user_index');
    }

    /**
     * @throws Exception
     */
    #[Route('/{id}/reset-password', name: 'admin_user_reset_password', methods: ['POST'])]
    public function resetPassword(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_user', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_user_edit', ['id' => $id]);
        }

        $user = $this->adminUserRepository->findById($id);
        if ($user === null) {
            $this->addFlash('error', 'Admin user not found.');

            return $this->redirectToRoute('admin_user_index');
        }

        $password        = (string) $request->request->get('password', '');
        $confirmPassword = (string) $request->request->get('confirm_password', '');
        $errors          = $this->validatePassword($password);

        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }

            return $this->redirectToRoute('admin_user_edit', ['id' => $id]);
        }

        $passwordHash = $this->hashPassword($password);
        $this->adminUserRepository->update($id, ['password_hash' => $passwordHash]);

        $actor = $this->getUser()?->getUserIdentifier() ?? 'system';
        $this->auditLogger->log('admin_user.reset_password', [
            'actor'    => $actor,
            'user_id'  => $id,
            'username' => (string) $user['username'],
        ]);
        $this->logger->info('Admin user password reset', [
            'actor'    => $actor,
            'user_id'  => $id,
            'username' => (string) $user['username'],
        ]);

        $this->addFlash('success', sprintf('Password for "%s" reset successfully.', (string) $user['username']));

        return $this->redirectToRoute('admin_user_edit', ['id' => $id]);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * @return array<int, string>
     */
    private function validateUsername(string $username): array
    {
        $errors = [];
        if ($username === '') {
            $errors[] = 'Username is required.';
        } elseif (!preg_match('/^[a-z0-9._-]{3,64}$/', $username)) {
            $errors[] = 'Username must be 3–64 characters and only use lowercase letters, digits, dots, hyphens, or underscores.';
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    private function validatePassword(string $password): array
    {
        $errors = [];
        if ($password === '') {
            $errors[] = 'Password is required.';
        } elseif (mb_strlen($password) < 12) {
            $errors[] = 'Password must be at least 12 characters.';
        }

        return $errors;
    }

    /**
     * @param array<mixed> $raw
     *
     * @return array<int, string>
     */
    private function filterRoles(array $raw): array
    {
        $filtered = [];
        foreach ($raw as $role) {
            if (is_string($role) && in_array($role, self::ALLOWED_ROLES, true)) {
                $filtered[] = $role;
            }
        }

        return array_values(array_unique($filtered));
    }

    private function hashPassword(string $plainPassword): string
    {
        // Temporary user object required by UserPasswordHasherInterface — only used for hashing
        $tempUser = new ControlPlaneUser(
            id: 0,
            username: '_temp',
            password: '',
            roles: [],
        );

        return $this->passwordHasher->hashPassword($tempUser, $plainPassword);
    }

    private function isAjax(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || str_contains(
                strtolower((string) $request->headers->get('Accept', '')),
                'application/json',
            );
    }

    /**
     * Decodes the JSON `roles` field in a user row to an array so templates can iterate over it.
     *
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    private function decodeUserRoles(array $user): array
    {
        $rolesRaw = $user['roles'] ?? '[]';
        $decoded  = json_decode(is_string($rolesRaw) ? $rolesRaw : '[]', true);
        $user['roles'] = is_array($decoded) ? $decoded : [];

        return $user;
    }
}
