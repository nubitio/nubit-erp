<?php

declare(strict_types=1);

namespace App\Billing\Despatch\Domain;

use App\Billing\Despatch\Entity\DespatchGuide;

/** Destinatario y puntos de partida y llegada de la guía. */
final class DespatchPartyRules
{
    /** @return list<string> */
    public function validate(DespatchGuide $guide): array
    {
        return [...$this->recipient($guide), ...$this->route($guide)];
    }

    /** @return list<string> */
    private function recipient(DespatchGuide $guide): array
    {
        $errors = [];

        if (trim($guide->getRecipientName()) === '') {
            $errors[] = 'Debe indicar el nombre o razón social del destinatario.';
        }

        $docError = DespatchDocumentRule::error(
            $guide->getRecipientDocType(),
            $guide->getRecipientDocNumber(),
            'del destinatario',
        );
        if ($docError !== null) {
            $errors[] = $docError;
        }

        return $errors;
    }

    /** @return list<string> */
    private function route(DespatchGuide $guide): array
    {
        $errors = [];

        if (preg_match('/^\d{6}$/', $guide->getOriginUbigeo()) !== 1) {
            $errors[] = 'El ubigeo del punto de partida debe tener 6 dígitos.';
        }
        if (preg_match('/^\d{6}$/', $guide->getDestinationUbigeo()) !== 1) {
            $errors[] = 'El ubigeo del punto de llegada debe tener 6 dígitos.';
        }
        if (trim($guide->getOriginAddress()) === '') {
            $errors[] = 'Debe indicar la dirección del punto de partida.';
        }
        if (trim($guide->getDestinationAddress()) === '') {
            $errors[] = 'Debe indicar la dirección del punto de llegada.';
        }

        return $errors;
    }
}
