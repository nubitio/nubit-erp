<?php

declare(strict_types=1);

namespace App\Billing\Controller;

use App\Billing\ApiResource\ConsultDocType;
use App\Billing\ApiResource\ConsultResource;
use App\Billing\Consult\Domain\LookupException;
use App\Billing\Consult\Infrastructure\CachedDocumentLookupService;
use Nubit\Platform\Exception\ServiceException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ConsultController extends AbstractController
{
    public function __construct(
        private readonly CachedDocumentLookupService $lookupService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $docTypeValue = $request->attributes->get('docType');
        $id = $request->attributes->get('id');

        $docType = ConsultDocType::from($docTypeValue);
        $data = $this->consult($id, $docType);

        return $this->json($data);
    }

    public function consult(string $id, ConsultDocType $docType): ConsultResource
    {
        try {
            $info = match ($docType) {
                ConsultDocType::RUC => $this->lookupService->lookupRuc($id),
                ConsultDocType::DNI => $this->lookupService->lookupDni($id),
            };

            return new ConsultResource(
                id: $info->id,
                name: $info->name,
                address: $info->address,
                active: $info->active,
            );
        } catch (LookupException $e) {
            throw new ServiceException($e->getMessage(), previous: $e);
        }
    }
}
