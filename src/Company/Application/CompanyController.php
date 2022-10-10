<?php

namespace App\Company\Application;

use App\Base\BaseController;
use App\Company\Domain\Company;
use App\Company\Domain\CompanyCreateUseCase;
use App\Company\Domain\CompanyDeleteUseCase;
use App\Company\Domain\CompanyGetAllUseCase;
use App\Company\Domain\CompanyUpdateUseCase;
use App\Service\FileUploader;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CompanyController extends BaseController
{
    private FileUploader $fileUploader;

    public function __construct(FileUploader $fileUploader)
    {
        parent::__construct();
        $this->fileUploader = $fileUploader;
    }

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
        $this->uploadLogo($request);
        /**
         * @var Company $company
         */
        $company = $this->serializer->deserialize(json_encode($request->request->all()), Company::class, 'json');
        $createUseCase($company);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/company/edit/{id}', name: 'company_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, CompanyUpdateUseCase $updateUseCase, $id): Response
    {
        $this->uploadLogo($request);
        $request->request->add(['empresa_id' => $id]);
        /**
         * @var Company $company
         */
        $company = $this->serializer->deserialize(json_encode($request->request->all()), Company::class, 'json');
        $updateUseCase($company);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/company/delete/{id}', name: 'company_delete', methods: ['DELETE', 'POST'])]
    public function delete(CompanyDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }

    private function uploadLogo(Request $request): void
    {
        $logoFile = $request->files->get('logo');
        if ($logoFile) {
            $logo = $this->fileUploader->upload($logoFile, Constants::IMAGE_UPLOAD_DIR);
            $request->request->set('logo', $logo);
        }
    }
}
