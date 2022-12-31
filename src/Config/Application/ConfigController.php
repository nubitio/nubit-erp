<?php

namespace App\Config\Application;

use App\Base\BaseController;
use App\Config\Domain\Config;
use App\Config\Domain\ConfigCreateUseCase;
use App\Config\Domain\ConfigDeleteUseCase;
use App\Config\Domain\ConfigGetAllUseCase;
use App\Config\Domain\ConfigUpdateUseCase;
use App\Core\Domain\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ConfigController extends BaseController
{
    #[Route('/config', name: 'config_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/index.html.twig');
    }

    #[Route('/config/get', name: 'config_list', methods: ['POST'])]
    public function getAll(ConfigGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/config/add', name: 'config_create', methods: ['POST'])]
    public function add(Request $request, ConfigCreateUseCase $createUseCase): Response
    {
        /**
         * @var Config $config
         */
        $config = $this->serializer->deserialize(json_encode($request->request->all()), Config::class, 'json');
        $createUseCase($config);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/config/edit/{id}', name: 'config_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, ConfigUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['id' => $id]);
        /**
         * @var Config $config
         */
        $config = $this->serializer->deserialize(json_encode($request->request->all()), Config::class, 'json');
        $updateUseCase($config);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/config/delete/{id}', name: 'config_delete', methods: ['DELETE', 'POST'])]
    public function delete(ConfigDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
