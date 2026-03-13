<?php

declare(strict_types=1);

namespace App\Admin\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/admin')]
final class LoginController extends AbstractController
{
    #[Route('/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si ya está autenticado, redirigir al dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'admin_logout', methods: ['GET', 'POST'])]
    public function logout(): never
    {
        // Symfony intercepta esta ruta — nunca se ejecuta
        throw new \LogicException('This method should never be reached.');
    }
}
