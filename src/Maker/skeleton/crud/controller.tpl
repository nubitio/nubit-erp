<?php

namespace App\{{module}}\Application;

use App\Base\BaseController;
use App\{{module}}\Domain\{{module}}GetAllUseCase;
use App\{{module}}\Domain\{{module}}CreateUseCase;
use App\{{module}}\Domain\{{module}}UpdateUseCase;
use App\{{module}}\Domain\{{module}}DeleteUseCase;
use App\{{module}}\Domain\{{module}};
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class {{module}}Controller extends BaseController
{
    #[Route('/{{kebabModule}}', name: '{{kebabModule}}_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('{{kebabModule}}/index.html.twig');
    }

    #[Route('/{{kebabModule}}/get', name: '{{kebabModule}}_list', methods: ['POST'])]
    public function getAll({{module}}GetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/{{kebabModule}}/add', name: '{{kebabModule}}_create', methods: ['POST'])]
    public function add(Request $request, {{module}}CreateUseCase $createUseCase): Response
    {
        /**
         * @var {{module}} ${{camelModule}}
         */
        ${{camelModule}} = $this->serializer->deserialize(json_encode($request->request->all()), {{module}}::class, 'json');
        $createUseCase(${{camelModule}});
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/{{kebabModule}}/edit/{id}', name: '{{kebabModule}}_update', methods: ['POST'])]
    public function edit(Request $request, {{module}}UpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['{{table_id}}' => $id]);
        /**
         * @var {{module}} ${{camelModule}}
         */
        ${{camelModule}} = $this->serializer->deserialize(json_encode($request->request->all()), {{module}}::class, 'json');
        $updateUseCase(${{camelModule}});
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/{{kebabModule}}/delete/{id}', name: '{{kebabModule}}_delete', methods: ['POST'])]
    public function delete({{module}}DeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
