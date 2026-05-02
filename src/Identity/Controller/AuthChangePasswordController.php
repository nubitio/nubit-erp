<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Dto\ChangePasswordInput;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security as SecurityComponent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthChangePasswordController extends AbstractController
{
    public function __construct(
        private readonly SecurityComponent $security,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Usuario no autenticado.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $input = new ChangePasswordInput();
        $input->currentPassword = trim((string) ($data['currentPassword'] ?? ''));
        $input->newPassword = trim((string) ($data['newPassword'] ?? ''));

        $violations = $this->validator->validate($input);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }

            return new JsonResponse(['message' => implode(' ', $errors)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $input->currentPassword)) {
            return new JsonResponse(['message' => 'La contraseña actual es incorrecta.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $input->newPassword));
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Contraseña actualizada correctamente.'], Response::HTTP_OK);
    }
}
