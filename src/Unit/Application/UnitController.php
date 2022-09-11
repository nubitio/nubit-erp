<?php

namespace App\Unit\Application;

use App\Base\BaseController;
use App\Unit\Domain\UnitGetAllUseCase;
use App\Unit\Domain\UnitCreateUseCase;
use App\Unit\Domain\UnitUpdateUseCase;
use App\Unit\Domain\UnitDeleteUseCase;
use App\Unit\Domain\Unit;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UnitController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Route('/unit', name: 'unit_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('unit/index.html.twig');
    }

    #[Route('/unit/get', name: 'unit_list', methods: ['POST'])]
    public function getAll(): Response
    {
        $getAllUseCase = $this->container->get(UnitGetAllUseCase::class);
        return $this->json($getAllUseCase());
    }

    #[Route('/unit/add', name: 'unit_create', methods: ['POST'])]
    public function add(Request $request): Response
    {
        /**
         * @var Unit $unit
         */
        $unit = $this->serializer->deserialize(json_encode($request->request->all()), Unit::class, 'json');
        $createUseCase = $this->container->get(UnitCreateUseCase::class);
        $createUseCase($unit);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/unit/edit/{id}', name: 'unit_update', methods: ['POST'])]
    public function edit(Request $request, $id): Response
    {
        $request->request->add(['unidad_id' => $id]);
        /**
         * @var Unit $unit
         */
        $unit = $this->serializer->deserialize(json_encode($request->request->all()), Unit::class, 'json');
        $updateUseCase = $this->container->get(UnitUpdateUseCase::class);
        $updateUseCase($unit);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/unit/delete/{id}', name: 'unit_delete', methods: ['POST'])]
    public function delete($id): Response
    {
        $deleteUseCase = $this->container->get(UnitDeleteUseCase::class);
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
