import {
  Alert,
  Badge,
  Button,
  DataTable,
  EmptyState,
  KpiMetricRow,
  Skeleton,
  type ContextMenuItem,
} from '@nubitio/ui';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { formatMoney } from '@shared/lib/displayFormat';
import toast from '@shared/lib/SharedUtils';
import { hasPermission } from '@shared/permissions';
import {
  treasuryErrorMessage,
  usePaymentMethodMapping,
  useSyncAccounts,
  useTreasuryPosition,
  type TreasuryAccount,
} from './api/treasuryApi';
import { KIND_LABEL, needsAccounts, suspenseBalances } from './model/treasuryModel';
import { NewWalletDialog } from './NewWalletDialog';
import { OpeningBalanceDialog } from './OpeningBalanceDialog';
import { TransferDialog } from './TransferDialog';

type Dialog =
  | { kind: 'wallet' }
  | { kind: 'transfer'; fromId?: string }
  | { kind: 'opening'; account: TreasuryAccount }
  | null;

interface Props {
  /** Switches the page to the payment-method tab, where suspense money gets its account. */
  onOpenMethods: () => void;
}

export const AccountsTab = ({ onOpenMethods }: Props) => {
  const navigate = useNavigate();
  const position = useTreasuryPosition();
  const sync = useSyncAccounts();
  const branches = usePaymentMethodMapping().data?.branches ?? [];
  const [dialog, setDialog] = useState<Dialog>(null);
  const canManage = hasPermission('treasury.manage');
  const canTransfer = hasPermission('treasury.transfer');

  const runSync = () =>
    sync.mutate(undefined, {
      onSuccess: ({ created, mapped }) =>
        toast(
          created.length === 0 && mapped === 0
            ? 'Las cuentas ya estaban al día'
            : `${created.length} cuenta(s) creadas y ${mapped} método(s) asignados`,
          'success',
        ),
      onError: (error) =>
        toast(treasuryErrorMessage(error, 'No se pudo sincronizar'), 'error', 6000),
    });

  if (position.isPending) return <Skeleton height={220} />;
  if (position.isError) {
    return (
      <Alert tone="danger">
        {treasuryErrorMessage(position.error, 'No se pudieron cargar las cuentas.')}{' '}
        <Button variant="secondary" size="sm" onClick={() => void position.refetch()}>
          Reintentar
        </Button>
      </Alert>
    );
  }

  const { accounts, totalsByCurrency } = position.data;
  const suspense = suspenseBalances(accounts);

  const rowActions = (account: TreasuryAccount): ContextMenuItem[] => {
    const items: ContextMenuItem[] = [];
    const isLedger = account.kind === 'bank' || account.kind === 'wallet';
    if (account.kind === 'cash_register') {
      items.push({ label: 'Ver en Caja', onClick: () => navigate('/cashbox/dashboard') });
    }
    if (account.kind === 'suspense') {
      items.push({ label: 'Asignar métodos de pago', onClick: onOpenMethods });
    }
    if (canManage && isLedger) {
      items.push({
        label: 'Saldo inicial',
        onClick: () => setDialog({ kind: 'opening', account }),
      });
    }
    if (canTransfer && (isLedger || account.kind === 'cash_register')) {
      items.push({
        label: 'Transferir desde aquí',
        onClick: () => setDialog({ kind: 'transfer', fromId: account.id }),
      });
    }

    return items;
  };

  return (
    <div className="treasury__stack">
      <div className="treasury__toolbar">
        {canManage && (
          <Button
            variant="secondary"
            icon="ph ph-arrows-clockwise"
            loading={sync.isPending}
            onClick={runSync}
          >
            Sincronizar cuentas
          </Button>
        )}
        {canManage && (
          <Button
            variant="secondary"
            icon="ph ph-plus"
            onClick={() => setDialog({ kind: 'wallet' })}
          >
            Nueva billetera
          </Button>
        )}
        {canTransfer && (
          <Button
            icon="ph ph-arrows-left-right"
            disabled={needsAccounts(accounts)}
            onClick={() => setDialog({ kind: 'transfer' })}
          >
            Transferir
          </Button>
        )}
      </div>

      {Object.keys(totalsByCurrency).length > 0 && (
        <KpiMetricRow
          layout="cards"
          items={Object.entries(totalsByCurrency).map(([currency, total]) => ({
            label: `Total en ${currency}`,
            value: formatMoney(total, currency),
            hint: 'Incluye cajas, bancos y billeteras',
          }))}
        />
      )}

      {suspense.map(([currency, balance]) => (
        <Alert key={currency} tone="warning">
          Hay {formatMoney(balance, currency)} sin asignar a una cuenta: son pagos con métodos que
          aún no tienen cuenta. Asígnalos en la pestaña Métodos de pago.
        </Alert>
      ))}

      {needsAccounts(accounts) && (
        <EmptyState
          size="sm"
          icon="bank"
          title="Aún no hay bancos ni billeteras"
          description="Sincroniza para traer tus cuentas bancarias y cajas, o crea una billetera como Yape o Plin."
        />
      )}

      <DataTable<TreasuryAccount>
        testId="treasury-accounts"
        rows={accounts}
        getRowKey={(account) => account.id}
        emptyMessage="No hay cuentas todavía. Usa Sincronizar cuentas."
        rowActions={rowActions}
        rowActionsLabel="Acciones de la cuenta"
        columns={[
          { id: 'name', header: 'Cuenta', render: (a) => a.name },
          {
            id: 'kind',
            header: 'Tipo',
            render: (a) => <Badge size="sm">{KIND_LABEL[a.kind]}</Badge>,
          },
          {
            id: 'branch',
            header: 'Sucursal',
            muted: true,
            accessor: (a) =>
              a.branchId === null
                ? 'Todas'
                : (branches.find((b) => b.id === a.branchId)?.name ?? '—'),
          },
          { id: 'currency', header: 'Moneda', accessor: (a) => a.currency },
          {
            id: 'balance',
            header: 'Saldo',
            align: 'right',
            render: (a) => formatMoney(a.balance, a.currency),
          },
        ]}
      />

      {dialog?.kind === 'wallet' && <NewWalletDialog onClose={() => setDialog(null)} />}
      {dialog?.kind === 'opening' && (
        <OpeningBalanceDialog account={dialog.account} onClose={() => setDialog(null)} />
      )}
      {dialog?.kind === 'transfer' && (
        <TransferDialog
          accounts={accounts}
          fromId={dialog.fromId}
          onClose={() => setDialog(null)}
        />
      )}
    </div>
  );
};
