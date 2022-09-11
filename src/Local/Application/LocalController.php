<?php

namespace App\Local\Application;

use App\Base\BaseController;
use App\Local\Domain\LocalGetAllUseCase;
use App\Local\Domain\LocalCreateUseCase;
use App\Local\Domain\LocalUpdateUseCase;
use App\Local\Domain\LocalDeleteUseCase;
use App\Local\Domain\Local;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LocalController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Route('/local', name: 'local_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('local/index.html.twig');
    }

    #[Route('/local/get', name: 'local_list', methods: ['POST'])]
    public function getAll(): Response
    {
        $getAllUseCase = $this->container->get(LocalGetAllUseCase::class);
        return $this->json($getAllUseCase());
    }

    #[Route('/local/add', name: 'local_create', methods: ['POST'])]
    public function add(Request $request): Response
    {
        /**
         * @var Local $local
         */
        $local = $this->serializer->deserialize(json_encode($request->request->all()), Local::class, 'json');
        $createUseCase = $this->container->get(LocalCreateUseCase::class);
        $createUseCase($local);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/local/edit/{id}', name: 'local_update', methods: ['POST'])]
    public function edit(Request $request, $id): Response
    {
        $request->request->add(['local_id' => $id]);
        /**
         * @var Local $local
         */
        $local = $this->serializer->deserialize(json_encode($request->request->all()), Local::class, 'json');
        $updateUseCase = $this->container->get(LocalUpdateUseCase::class);
        $updateUseCase($local);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/local/delete/{id}', name: 'local_delete', methods: ['POST'])]
    public function delete($id): Response
    {
        $deleteUseCase = $this->container->get(LocalDeleteUseCase::class);
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
