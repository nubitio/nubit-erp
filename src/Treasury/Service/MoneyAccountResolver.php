<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\AccountNotConfigured;
use App\Treasury\Domain\TreasuryAccount;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Port\MoneyMovementRequest;
use App\Treasury\Repository\TreasuryAccountRepository;

/**
 * Decides which account a request touches (decision 4):
 *  1. the account the user picked at payment time, if any;
 *  2. cash: the register of the user's open session;
 *  3. otherwise the account mapped to the payment method in the branch;
 *  4. otherwise the branch's suspense account for that currency, with a warning, so a sale is never blocked
 *     by missing configuration (offline POS cannot ask the cashier later).
 */
final readonly class MoneyAccountResolver
{
    public function __construct(
        private TreasuryAccountRepository $accounts,
        private DrawerReader $drawers,
    ) {}

    /** @return array{account: TreasuryAccount, warning: ?string} */
    public function resolve(MoneyMovementRequest $request): array
    {
        if ($request->accountId !== null) {
            $account = $this->accounts->find($request->accountId);
            if ($account === null || !$account->active) {
                throw new TreasuryException('La cuenta elegida no existe o está inactiva.');
            }

            return ['account' => $account, 'warning' => null];
        }

        if ($request->isCash) {
            return ['account' => $this->openDrawerAccount($request), 'warning' => null];
        }

        if ($request->paymentMethodId !== null) {
            $mapped = $this->accounts->mappedAccountId($request->branchId, $request->paymentMethodId);
            $account = $mapped === null ? null : $this->accounts->find($mapped);
            if ($account !== null && $account->active) {
                return ['account' => $account, 'warning' => null];
            }
        }

        $suspense = $this->accounts->findSuspense($request->branchId, $request->currency);
        if ($suspense === null) {
            throw new TreasuryException(sprintf(
                'No hay cuenta de suspenso en %s para la sucursal %s; ejecuta la configuración inicial de cuentas.',
                $request->currency,
                $request->branchId,
            ));
        }

        $reason = $request->paymentMethodId === null
            ? 'El movimiento no indica método de pago.'
            : AccountNotConfigured::for($request->paymentMethodId, $request->branchId)->getMessage();

        return ['account' => $suspense, 'warning' => $reason . ' Quedó en la cuenta de suspenso hasta reclasificarlo.'];
    }

    private function openDrawerAccount(MoneyMovementRequest $request): TreasuryAccount
    {
        if ($request->userId === null) {
            throw new TreasuryException('Un movimiento en efectivo necesita el usuario que lo registra.');
        }
        $registerId = $this->drawers->openRegisterIdFor($request->userId);
        if ($registerId === null) {
            throw new TreasuryException('No tienes una caja abierta; abre una sesión de caja para registrar efectivo.');
        }
        $account = $this->accounts->findByCashRegister($registerId);
        if ($account === null || $account->kind !== AccountKind::CashRegister) {
            throw new TreasuryException(
                'La caja abierta no tiene cuenta de dinero; ejecuta la configuración inicial de cuentas.',
            );
        }

        return $account;
    }
}
