<?php

namespace App\Auth\Application;

use App\Base\BaseController;
use App\Local\Domain\LocalGetOneByIdUseCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends BaseController
{
    #[Route('/login', name: 'login_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($request->getSession()->get('localId')) {
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
    public function setLocal(Request $request, LocalGetOneByIdUseCase $localGetOneById): Response
    {
        $data = json_decode($request->getContent());
        $localId = $data->localId;

        $local = $localGetOneById($localId);

        $session = $request->getSession();
        $session->set('localId', $localId);
        $session->set('localName', $local->descripcion);

        return $this->json([
            'success' => true,
            'localId' => $localId,
        ]);
    }
}