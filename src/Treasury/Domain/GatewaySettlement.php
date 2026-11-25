<?php

declare(strict_types=1);

namespace App\Treasury\Domain;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class GatewaySettlement
{
    public string $clearingId;
    public string $bankId;

    /** @var list<string> */
    public array $captureIds;

    /** @param list<string> $captureIds */
    public function __construct(
        string $clearingId,
        string $bankId,
        public string $reference,
        public int $netMinor,
        public int $feeMinor,
        public DateTimeImmutable $paidAt,
        public ?string $feeCode,
        array $captureIds,
    ) {
        if (
            !Uuid::isValid($clearingId)
            || !Uuid::isValid($bankId)
            || preg_match('/\A[A-Za-z0-9._:-]{1,120}\z/', $reference) !== 1
            || $netMinor < 1
            || $feeMinor < 0
            || $netMinor > 999_999_999_999
            || $feeMinor > (999_999_999_999 - $netMinor)
            || $feeMinor > 0 !== ($feeCode !== null)
        ) {
            throw new TreasuryException('Indica cuentas, referencia, importes y cuenta de comisión válidos.');
        }

        $ids = array_map(strtolower(...), $captureIds);
        if ($ids === [] || count($ids) > 1000 || count($ids) !== count(array_unique($ids))) {
            throw new TreasuryException('Indica entre 1 y 1000 capturas distintas.');
        }
        foreach ($ids as $id) {
            if (!Uuid::isValid($id)) {
                throw new TreasuryException('Cada captura debe identificar un movimiento de Tesorería.');
            }
        }
        sort($ids, SORT_STRING);
        $this->captureIds = $ids;
        $this->clearingId = strtolower($clearingId);
        $this->bankId = strtolower($bankId);
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            $this->bankId,
            $this->netMinor,
            $this->feeMinor,
            $this->paidAt->format('Y-m-d H:i:s'),
            $this->feeCode,
            $this->captureIds,
        ], JSON_THROW_ON_ERROR));
    }
}
