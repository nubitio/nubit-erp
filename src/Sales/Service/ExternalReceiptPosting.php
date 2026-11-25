<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Accounting\Entity\Account;
use App\Accounting\Message\EconomicEvent;
use App\Accounting\Repository\AccountRepository;
use App\Accounting\Service\DefaultPostingService;
use App\Accounting\Service\JournalPoster;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\Party;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Port\MoneyMovementRequest;
use App\Treasury\Port\RecordsMoneyMovement;
use App\Treasury\Repository\GatewayRouteRepository;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ExternalReceiptPosting
{
    public function __construct(
        private RecordsMoneyMovement $treasury,
        private MessageBusInterface $bus,
        private GatewayRouteRepository $gatewayRoutes,
        private AccountRepository $accounts,
        private JournalPoster $journal,
    ) {}

    public function book(
        SaleRecord $sale,
        SalePayment $payment,
        string $branchId,
        int $methodId,
        string $amount,
        DateTimeImmutable $paidAt,
        string $reference,
        bool $gateway,
    ): int {
        $id = $payment->getId() ?? throw new \LogicException('The payment must be stored before posting.');
        $captureKey = $payment->getIdempotencyKey() ?? throw new \LogicException('The payment has no capture key.');
        $description = 'Cobro externo ' . $reference;
        $customerId = $sale->getCustomer()?->getId();
        $route = $gateway ? $this->gatewayRoutes->find($branchId, $methodId, $payment->getCurrency()) : null;
        if ($gateway && $route === null) {
            throw new UnprocessableEntityHttpException(
                'Configura una ruta de pasarela activa para este medio de pago.',
            );
        }
        try {
            $movement = $this->treasury->record(new MoneyMovementRequest(
                key: new MovementKey(MovementSource::SalePayment, $captureKey),
                direction: Direction::In,
                amount: $amount,
                currency: $payment->getCurrency(),
                branchId: $branchId,
                occurredAt: $paidAt,
                paymentMethodId: $methodId,
                party: $customerId === null ? null : new Party('customer', (string) $customerId),
                reference: $reference,
                description: $description,
                accountId: $route['account_id'] ?? null,
                requiredAccountKind: $gateway ? AccountKind::Clearing : AccountKind::Bank,
            ));
        } catch (TreasuryException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
        if ($movement === null || $movement->duplicate) {
            throw new UnprocessableEntityHttpException('No se pudo registrar un nuevo cobro externo en Tesorería.');
        }

        if ($route !== null) {
            $code = $route['ledger_code'];
            $account = $this->accounts->findPostableByCode($code);
            if (
                $account?->getType() !== Account::ASSET
                || in_array(
                    $code,
                    [
                        DefaultPostingService::CASH,
                        DefaultPostingService::BANK,
                        DefaultPostingService::RECEIVABLE,
                    ],
                    strict: true,
                )
            ) {
                throw new UnprocessableEntityHttpException(
                    'Configura una cuenta contable de activo para fondos por liquidar.',
                );
            }
            $this->journal->post(
                EconomicEvent::SOURCE_RECEIVABLE_COLLECT,
                (string) $id,
                $paidAt,
                $description,
                [
                    ['code' => $code, 'debit' => $amount],
                    ['code' => DefaultPostingService::RECEIVABLE, 'credit' => $amount],
                ],
                $payment->getCurrency(),
            );

            return $id;
        }

        $this->bus->dispatch(new EconomicEvent(
            source: EconomicEvent::SOURCE_RECEIVABLE_COLLECT,
            sourceId: (string) $id,
            kind: EconomicEvent::RECEIVABLE_COLLECT_BANK,
            occurredOn: $paidAt->format('Y-m-d'),
            description: $description,
            total: $amount,
            currency: $payment->getCurrency(),
        ));

        return $id;
    }
}
