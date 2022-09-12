<?php

namespace App\Company\Application;

use App\Base\BaseController;
use App\Company\Domain\CompanyGetAllUseCase;
use App\Company\Domain\CompanyCreateUseCase;
use App\Company\Domain\CompanyUpdateUseCase;
use App\Company\Domain\CompanyDeleteUseCase;
use App\Company\Domain\Company;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CompanyController extends BaseController
{
    #[Route('/company', name: 'company_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('company/index.html.twig');
    }

    #[Route('/company/get', name: 'company_list', methods: ['POST'])]
    public function getAll(CompanyGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/company/add', name: 'company_create', methods: ['POST'])]
    public function add(Request $request, CompanyCreateUseCase $createUseCase): Response
    {
        /**
         * @var Company $company
         */
        $company = $this->serializer->deserialize(json_encode($request->request->all()), Company::class, 'json');
        $createUseCase($company);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/company/edit/{id}', name: 'company_update', methods: ['POST'])]
    public function edit(Request $request, CompanyUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['empresa_id' => $id]);
        /**
         * @var Company $company
         */
        $company = $this->serializer->deserialize(json_encode($request->request->all()), Company::class, 'json');
        $updateUseCase($company);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/company/delete/{id}', name: 'company_delete', methods: ['POST'])]
    public function delete(CompanyDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
