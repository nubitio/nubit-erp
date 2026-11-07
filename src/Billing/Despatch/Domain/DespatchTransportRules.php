<?php

declare(strict_types=1);

namespace App\Billing\Despatch\Domain;

use App\Billing\Despatch\Entity\DespatchGuide;

/** Datos del transporte exigidos según la modalidad de traslado (Catálogo 18). */
final class DespatchTransportRules
{
    private const string MODE_PUBLIC = '01';

    private const string MODE_PRIVATE = '02';

    /** @return list<string> */
    public function validate(DespatchGuide $guide): array
    {
        return match ($guide->getTransportMode()) {
            self::MODE_PUBLIC => $this->publicTransport($guide),
            self::MODE_PRIVATE => $this->privateTransport($guide),
            default => [sprintf('La modalidad de traslado "%s" no es válida.', $guide->getTransportMode())],
        };
    }

    /** @return list<string> */
    private function publicTransport(DespatchGuide $guide): array
    {
        $errors = [];

        $docError = DespatchDocumentRule::error(
            (string) $guide->getCarrierDocType(),
            (string) $guide->getCarrierDocNumber(),
            'del transportista',
        );
        if ($docError !== null) {
            $errors[] = $docError;
        }
        if (trim((string) $guide->getCarrierName()) === '') {
            $errors[] = 'Debe indicar la razón social del transportista.';
        }

        return $errors;
    }

    /** @return list<string> */
    private function privateTransport(DespatchGuide $guide): array
    {
        $errors = [];

        if (preg_match('/^[A-Z0-9]{6,7}$/', strtoupper((string) $guide->getVehiclePlate())) !== 1) {
            $errors[] = 'Debe indicar la placa del vehículo (6 o 7 caracteres alfanuméricos).';
        }

        $docError = DespatchDocumentRule::error(
            (string) $guide->getDriverDocType(),
            (string) $guide->getDriverDocNumber(),
            'del conductor',
        );
        if ($docError !== null) {
            $errors[] = $docError;
        }
        if (trim((string) $guide->getDriverNames()) === '' || trim((string) $guide->getDriverLastNames()) === '') {
            $errors[] = 'Debe indicar nombres y apellidos del conductor.';
        }
        if (trim((string) $guide->getDriverLicense()) === '') {
            $errors[] = 'Debe indicar la licencia de conducir del conductor.';
        }

        return $errors;
    }
}
