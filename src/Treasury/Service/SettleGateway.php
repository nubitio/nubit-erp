<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\Accounting\Entity\Account;
use App\Accounting\Repository\AccountRepository;
use App\Accounting\Service\DefaultPostingService;
use App\Accounting\Service\JournalPoster;
use App\Efact\Domain\Money;
use App\Settings\Capability\CapabilityKey;
use App\Settings\Capability\TenantCapabilityResolver;
use App\Treasury\Domain\GatewaySettlement;
use App\Treasury\Domain\Leg;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Domain\TreasuryMovement;
use App\Treasury\Repository\GatewaySettlementRepository;
use App\Treasury\Repository\TreasuryMovementRepository;
use Brick\Math\BigDecimal;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class SettleGateway
{
    public function __construct(
        private Connection $connection,
        private GatewaySettlementRepository $settlements,
        private TreasuryMovementRepository $movements,
        private AccountRepository $accounts,
        private JournalPoster $journal,
        private TenantCapabilityResolver $capabilities,
    ) {}

    public function settle(GatewaySettlement $settlement): string
    {
        $this->capabilities->require(CapabilityKey::TreasuryAccounts);

        return $this->connection->transactional(function () use ($settlement): string {
            // Serialize competing allocations and retries for a clearing account.
            $this->connection->fetchOne('SELECT id FROM treasury_account WHERE id = ? FOR UPDATE', [
                $settlement->clearingId,
            ]);
            $existing = $this->settlements->find($settlement);
            if ($existing !== false) {
                if ($existing['payload_hash'] !== $settlement->fingerprint()) {
                    throw new TreasuryException('La referencia de liquidación ya tiene otros datos.');
                }

                return (string) $existing['id'];
            }

            $this->requireActiveAccounts($settlement);
            $lines = $this->journalLines($settlement);
            $id = Uuid::v7()->toRfc4122();
            $this->settlements->save($id, $settlement);
            $this->appendLeg(
                $settlement,
                $id,
                Leg::Out,
                $settlement->clearingId,
                -($settlement->netMinor + $settlement->feeMinor),
            );
            $this->appendLeg($settlement, $id, Leg::In, $settlement->bankId, $settlement->netMinor);
            $this->journal->post(
                MovementSource::GatewaySettlement->value,
                $id,
                $settlement->paidAt,
                'Liquidación de pasarela ' . $settlement->reference,
                $lines,
            );

            return $id;
        });
    }

    private function requireActiveAccounts(GatewaySettlement $settlement): void
    {
        $bank = $this->connection->fetchOne(
            <<<'SQL'
                SELECT b.id
                FROM treasury_account c
                JOIN treasury_account b ON b.id = ? AND b.kind = 'bank' AND b.active = 1 AND b.currency = c.currency
                    AND b.bank_account_id IS NOT NULL
                    AND (b.branch_id IS NULL OR c.branch_id IS NULL OR b.branch_id = c.branch_id)
                JOIN bank_account bank ON bank.id = b.bank_account_id AND bank.active = 1 AND bank.currency = b.currency
                WHERE c.id = ? AND c.kind = 'clearing' AND c.active = 1 AND c.currency = 'PEN'
                FOR UPDATE
                SQL,
            [$settlement->bankId, $settlement->clearingId],
        );
        if ($bank === false) {
            throw new TreasuryException(
                'Selecciona cuentas de fondos por liquidar y banco activas y compatibles en PEN.',
            );
        }
    }

    /** @return list<array{code: string, debit?: string, credit?: string}> */
    private function journalLines(GatewaySettlement $settlement): array
    {
        $captures = $this->settlements->eligibleCaptures($settlement);
        if (count($captures) !== count($settlement->captureIds)) {
            throw new TreasuryException(
                'Hay capturas inexistentes, revertidas, ya liquidadas o fuera de la cuenta o fecha.',
            );
        }

        $credits = [];
        $grossMinor = 0;
        foreach ($captures as $capture) {
            $code = (string) $capture['code'];
            $minor = Money::toMinor(Money::money($capture['signed_amount']));
            $grossMinor += $minor;
            $credits[$code] = ($credits[$code] ?? 0) + $minor;
        }

        if ($grossMinor !== ($settlement->netMinor + $settlement->feeMinor)) {
            throw new TreasuryException('Las capturas deben sumar el abono neto más la comisión.');
        }

        $lines = [['code' => DefaultPostingService::BANK, 'debit' => self::decimal($settlement->netMinor)]];
        if ($settlement->feeCode !== null) {
            if ($this->accounts->findPostableByCode($settlement->feeCode)?->getType() !== Account::EXPENSE) {
                throw new TreasuryException('La comisión requiere una cuenta contable de gasto activa.');
            }
            $lines[] = ['code' => $settlement->feeCode, 'debit' => self::decimal($settlement->feeMinor)];
        }
        foreach ($credits as $code => $minor) {
            $lines[] = ['code' => (string) $code, 'credit' => self::decimal($minor)];
        }

        return $lines;
    }

    private function appendLeg(GatewaySettlement $settlement, string $id, Leg $leg, string $account, int $amount): void
    {
        $movement = new TreasuryMovement(
            id: Uuid::v7()->toRfc4122(),
            accountId: $account,
            signedAmount: self::decimal($amount),
            currency: 'PEN',
            occurredAt: $settlement->paidAt,
            key: new MovementKey(MovementSource::GatewaySettlement, $id, $leg),
            party: null,
            reference: $settlement->reference,
            description: 'Liquidación de pasarela ' . $settlement->reference,
            transferId: $id,
            correctsMovementId: null,
            origin: 'live',
            createdBy: null,
        );
        if (!$this->movements->append($movement)) {
            throw new TreasuryException('No se pudieron registrar los movimientos de la liquidación.');
        }
    }

    private static function decimal(int $minor): string
    {
        return (string) BigDecimal::of($minor)->withPointMovedLeft(2)->toScale(2);
    }
}
