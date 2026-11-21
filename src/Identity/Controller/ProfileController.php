<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Profile\AvatarService;
use App\Identity\Profile\ProfileService;
use App\Identity\Profile\ProfileView;
use App\Identity\Profile\ProfileViolations;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "My profile": any signed-in user, about themselves only. There is no id in these routes on purpose,
 * so no request can reach another user's data. Errors use the API Platform violation shape, which the
 * web forms already know how to place under their fields.
 */
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly ProfileService $profile,
        private readonly AvatarService $avatars,
    ) {}

    #[Route('/api/profile', name: 'api_profile_get', methods: ['GET'])]
    public function show(): Response
    {
        $user = $this->currentUser();

        return $user instanceof User ? $this->json(ProfileView::of($user)) : $this->unauthenticated();
    }

    #[Route('/api/profile', name: 'api_profile_update', methods: ['PATCH'])]
    public function update(Request $request): Response
    {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->unauthenticated();
        }

        return $this->guard(function () use ($user, $request): Response {
            $this->profile->update($user, $this->payload($request));

            return $this->json(ProfileView::of($user));
        });
    }

    #[Route('/api/profile/email', name: 'api_profile_email', methods: ['POST'])]
    public function changeEmail(Request $request): Response
    {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->unauthenticated();
        }
        $payload = $this->payload($request);
        $email = is_string($payload['email'] ?? null) ? $payload['email'] : '';
        $password = is_string($payload['currentPassword'] ?? null) ? $payload['currentPassword'] : '';

        return $this->guard(function () use ($user, $email, $password): Response {
            $this->profile->changeEmail($user, $email, $password);

            return $this->json(ProfileView::of($user));
        });
    }

    #[Route('/api/profile/avatar', name: 'api_profile_avatar_upload', methods: ['POST'])]
    public function uploadAvatar(Request $request): Response
    {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->unauthenticated();
        }
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->violations(['file' => 'Selecciona una imagen.']);
        }

        return $this->guard(function () use ($user, $file): Response {
            $this->avatars->set($user, $file);

            return $this->json(ProfileView::of($user));
        });
    }

    #[Route('/api/profile/avatar', name: 'api_profile_avatar_remove', methods: ['DELETE'])]
    public function removeAvatar(): Response
    {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->unauthenticated();
        }
        $this->avatars->remove($user);

        return $this->json(ProfileView::of($user));
    }

    #[Route('/api/profile/avatar', name: 'api_profile_avatar', methods: ['GET'])]
    public function avatar(): Response
    {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->unauthenticated();
        }
        $avatar = $this->avatars->read($user);
        if ($avatar === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $response = new Response($avatar['content'], Response::HTTP_OK, [
            'Content-Type' => $avatar['mime'],
            // Defence in depth: the bytes are an image we validated, but never let a browser sniff them as anything else.
            'X-Content-Type-Options' => 'nosniff',
        ]);
        // The URL carries ?v=<upload time>, so a changed photo is a new URL and this can be cached hard.
        $response->setPrivate();
        $response->setMaxAge(31_536_000);

        return $response;
    }

    private function currentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);
        if (!is_array($decoded)) {
            return [];
        }

        $payload = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /** @param callable(): Response $action */
    private function guard(callable $action): Response
    {
        try {
            return $action();
        } catch (ProfileViolations $exception) {
            return $this->violations($exception->violations);
        }
    }

    /** @param array<string, string> $violations */
    private function violations(array $violations): JsonResponse
    {
        return new JsonResponse([
            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
            'violations' => array_map(
                static fn(string $field, string $message): array => ['propertyPath' => $field, 'message' => $message],
                array_keys($violations),
                $violations,
            ),
            'detail' => implode(' ', $violations),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function unauthenticated(): JsonResponse
    {
        return new JsonResponse(['message' => 'Authentication Required'], Response::HTTP_UNAUTHORIZED);
    }
}
