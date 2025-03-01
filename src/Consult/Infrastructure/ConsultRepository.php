<?php

namespace App\Consult\Infrastructure;

use App\Consult\Domain\Consult;
use App\Consult\Domain\ConsultRepositoryInterface;
use App\Consult\Domain\DocType;
use App\Core\Domain\Exception\ServiceException;
use Symfony\Component\HttpClient\HttpClient;

class ConsultRepository implements ConsultRepositoryInterface
{
    private const string API_URL = 'https://consultapi.efact.app/api/v1/';

    #[\Override]
    public function consult(string $id, DocType $docType): Consult
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
            DocType::DNI => $this->mapDni($data),
            DocType::RUC => $this->mapRuc($data),
        };
    }

    /**
     * @param array<string, bool|float|int|string|null> $data
     */
    private function mapDni(array $data): Consult
    {
        return new Consult(
            strval($data['dni']),
            $data['nombres'] . ' ' . $data['apellidoPaterno'] . ' ' . $data['apellidoMaterno'],
            '',
            true
        );
    }

    /**
     * @param array<string, bool|float|int|string|null> $data
     */
    private function mapRuc(array $data): Consult
    {
        return new Consult(
            strval($data['ruc']),
            strval($data['razonSocial']),
            strval($data['direccion']),
            'ACTIVO' === strval($data['estado'])
        );
    }
}
