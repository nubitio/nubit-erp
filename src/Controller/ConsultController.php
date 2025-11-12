<?php

namespace App\Controller;

use App\ApiResource\ConsultDocType;
use App\ApiResource\ConsultResource;
use App\Core\Exception\ServiceException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ConsultController extends AbstractController
{
    private const string API_URL = 'https://consultapi.efact.app/api/v1/';

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
        $url = self::API_URL . $docType->value . '/' . $id . '?token=abcxyz';
        $httpClient = HttpClient::create();
        $response = $httpClient->request('GET', $url);
        if (200 !== $response->getStatusCode()) {
            throw new ServiceException('No se pudo consultar el documento, intente nuevamente');
        }

        /** @var array<string, bool|float|int|string|null> $data */
        $data = $response->toArray();

        return match ($docType) {
            ConsultDocType::DNI => $this->mapDni($data),
            ConsultDocType::RUC => $this->mapRuc($data),
        };
    }

    /**
     * @param array<string, bool|float|int|string|null> $data
     */
    private function mapDni(array $data): ConsultResource
    {
        return new ConsultResource(
            strval($data['dni']),
            $data['nombres'] . ' ' . $data['apellidoPaterno'] . ' ' . $data['apellidoMaterno'],
            '',
            true
        );
    }

    /**
     * @param array<string, bool|float|int|string|null> $data
     */
    private function mapRuc(array $data): ConsultResource
    {
        return new ConsultResource(
            strval($data['ruc']),
            strval($data['razonSocial']),
            strval($data['direccion']),
            'ACTIVO' === strval($data['estado'])
        );
    }
}
