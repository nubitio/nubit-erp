<?php

namespace App\Customer\Application;

use App\Base\BaseController;
use App\Customer\Domain\Customer;
use App\Customer\Domain\CustomerCreateUseCase;
use App\Customer\Domain\CustomerDeleteUseCase;
use App\Customer\Domain\CustomerGetAllUseCase;
use App\Customer\Domain\CustomerUpdateUseCase;
use App\Service\XlsExporter;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class CustomerController extends BaseController
{
    #[Route('/customer', name: 'customer_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('customer/index.html.twig');
    }

    #[Route('/customer/get', name: 'customer_list', methods: ['POST'])]
    public function getAll(CustomerGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/customer/add', name: 'customer_create', methods: ['POST'])]
    public function add(Request $request, CustomerCreateUseCase $createUseCase): Response
    {
        /**
         * @var Customer $customer
         */
        $customer = $this->serializer->deserialize(json_encode($request->request->all()), Customer::class, 'json');
        $createUseCase($customer);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/customer/edit/{id}', name: 'customer_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, CustomerUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['ruc' => $id]);
        /**
         * @var Customer $customer
         */
        $customer = $this->serializer->deserialize(json_encode($request->request->all()), Customer::class, 'json');
        $updateUseCase($customer);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/customer/delete/{id}', name: 'customer_delete', methods: ['DELETE', 'POST'])]
    public function delete(CustomerDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }

    #[Route('/customer/export', name: 'customer_export', methods: ['GET'])]
    public function export(Request $request, CustomerGetAllUseCase $getAllUseCase, XlsExporter $exporter): StreamedResponse
    {
        $filterRules = json_decode($request->query->get('filterRules'), true);
        $customers = $getAllUseCase($filterRules);

        return $exporter->export($customers['rows'], 'customers');
    }
}
