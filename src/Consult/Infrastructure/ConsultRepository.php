<?php

namespace App\Consult\Infrastructure;

use App\Consult\Domain\Consult;
use App\Consult\Domain\ConsultRepositoryInterface;
use App\Consult\Domain\DocType;
use Symfony\Component\HttpClient\HttpClient;

class ConsultRepository implements ConsultRepositoryInterface
{
    private const API_URL = 'https://consultapi.efact.app/api/v1/';

    public function consult(string $id, DocType $docType): Consult
    {
        $url = self::API_URL . $docType->value . '/' . $id . '?token=abcxyz';
        $httpClient = HttpClient::create();
        $response = $httpClient->request('GET', $url);
        $data = $response->toArray();

        if ($docType === DocType::DNI) {
            return $this->mapDni($data);
        }

        if ($docType === DocType::RUC) {
            return $this->mapRuc($data);
        }

        throw new \Exception('Invalid DocType');
    }

    /**
     * @param array<string, mixed> $data
     * @return Consult
     */
    private function mapDni(array $data): Consult
    {
        return new Consult(
            $data['dni'],
            $data['nombres'] . ' ' . $data['apellidoPaterno'] . ' ' . $data['apellidoMaterno'],
            '',
            true
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return Consult
     */
    private function mapRuc(array $data): Consult
    {
        return new Consult(
            $data['ruc'],
            $data['razonSocial'],
            $data['direccion'],
            $data['estado'] === 'ACTIVO'
        );
    }
}