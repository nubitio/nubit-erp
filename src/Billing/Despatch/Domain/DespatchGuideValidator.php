<?php

declare(strict_types=1);

namespace App\Billing\Despatch\Domain;

use App\Billing\Despatch\Entity\DespatchGuide;

/**
 * Valida los datos exigidos por SUNAT para una Guía de Remisión Remitente
 * antes de persistirla, para que el usuario vea el problema al registrar y no
 * como un rechazo tras el envío.
 *
 * Solo cubre reglas de formato y de obligatoriedad según el modo de traslado;
 * no sustituye la validación de SUNAT. Las reglas de partes y de transporte
 * viven en {@see DespatchPartyRules} y {@see DespatchTransportRules}.
 */
final readonly class DespatchGuideValidator
{
    /** Catálogo 20 — motivos de traslado admitidos. */
    public const array TRANSFER_REASONS = [
        '01',
        '02',
        '03',
        '04',
        '05',
        '06',
        '07',
        '08',
        '09',
        '13',
        '14',
        '17',
        '18',
        '19',
    ];

    /** Motivo "Otros": SUNAT exige una descripción. */
    private const string REASON_OTHER = '13';

    public function __construct(
        private DespatchPartyRules $parties = new DespatchPartyRules(),
        private DespatchTransportRules $transport = new DespatchTransportRules(),
    ) {}

    /**
     * @return list<string> Mensajes de error en español; vacío si la guía es válida
     */
    public function validate(DespatchGuide $guide): array
    {
        return [
            ...$this->header($guide),
            ...$this->parties->validate($guide),
            ...$this->transport->validate($guide),
            ...$this->cargo($guide),
        ];
    }

    /** @return list<string> */
    private function header(DespatchGuide $guide): array
    {
        $errors = [];

        if (preg_match('/^T[A-Z0-9]{3}$/', $guide->getSeries()) !== 1) {
            $errors[] = 'La serie de la guía debe tener 4 caracteres y empezar con T (ej. T001).';
        }

        if ($guide->getTransferDate() < $guide->getIssueDate()) {
            $errors[] = 'La fecha de inicio del traslado no puede ser anterior a la fecha de emisión.';
        }

        if (!in_array($guide->getTransferReason(), self::TRANSFER_REASONS, true)) {
            $errors[] = sprintf('El motivo de traslado "%s" no es válido.', $guide->getTransferReason());
        }

        if (
            $guide->getTransferReason() === self::REASON_OTHER
            && trim((string) $guide->getTransferReasonDescription()) === ''
        ) {
            $errors[] = 'El motivo "Otros" requiere una descripción del traslado.';
        }

        return $errors;
    }

    /** @return list<string> */
    private function cargo(DespatchGuide $guide): array
    {
        $errors = [];

        if ((float) $guide->getTotalWeight() <= 0) {
            $errors[] = 'El peso total debe ser mayor a cero.';
        }

        foreach ($guide->getLines() as $index => $line) {
            $position = $index + 1;
            if (trim($line->getDescription()) === '') {
                $errors[] = sprintf('El ítem %d no tiene descripción.', $position);
            }
            if ((float) $line->getQuantity() <= 0) {
                $errors[] = sprintf('El ítem %d debe tener una cantidad mayor a cero.', $position);
            }
            if (trim($line->getUnit()) === '') {
                $errors[] = sprintf('El ítem %d no tiene unidad de medida.', $position);
            }
        }

        return $errors;
    }
}
