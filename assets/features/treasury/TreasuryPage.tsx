import { Button, EmptyState, Page, PageHeader, Skeleton, Tabs } from '@nubitio/ui';
import { useState } from 'react';
import { useTenantRuntimeConfig } from '@shared/hooks/useTenantRuntimeConfig';
import toast from '@shared/lib/SharedUtils';
import { hasPermission } from '@shared/permissions';
import { treasuryErrorMessage, useSetTreasuryCapability } from './api/treasuryApi';
import { AccountsTab } from './AccountsTab';
import { MappingTab } from './MappingTab';
import './TreasuryPage.scss';

type TabKey = 'accounts' | 'methods';

/** Shown while Treasury is off: whoever may manage it can switch it on from here. */
const TreasuryOff = () => {
  const activate = useSetTreasuryCapability();

  return (
    <Page>
      <EmptyState
        icon="bank"
        title="Cuentas de dinero no está habilitado"
        description={
          hasPermission('treasury.manage')
            ? 'Al activarlo se crean las cuentas de tus cajas y bancos, y desde entonces los pagos se registran en ellas. Los pagos anteriores no se reconstruyen: registra el saldo inicial de cada cuenta.'
            : 'Pide al administrador que active esta función para tu negocio.'
        }
        action={
          hasPermission('treasury.manage') ? (
            <Button
              loading={activate.isPending}
              disabled={activate.isPending}
              onClick={() =>
                activate.mutate(true, {
                  onSuccess: () => toast('Cuentas de dinero activadas', 'success'),
                  onError: (error) =>
                    toast(treasuryErrorMessage(error, 'No se pudo activar'), 'error', 6000),
                })
              }
            >
              Activar cuentas de dinero
            </Button>
          ) : undefined
        }
      />
    </Page>
  );
};

/**
 * Cuentas de dinero (ADR-021, Treasury): where money sits, how much, and which account each payment method
 * lands in. Cash drawers appear here with the balance of their open session; their movements stay in Caja.
 */
export const TreasuryPage = () => {
  const [tab, setTab] = useState<TabKey>('accounts');
  const config = useTenantRuntimeConfig();

  if (config.isPending) return <Skeleton height={240} />;
  if (config.data?.capabilities['treasury.accounts']?.enabled !== true) {
    return <TreasuryOff />;
  }

  return (
    <Page wide className="treasury">
      <PageHeader
        title="Cuentas de dinero"
        subtitle="Cuánto tienes en caja, bancos y billeteras, y a qué cuenta entra cada método de pago."
      />
      <Tabs<TabKey>
        aria-label="Secciones de cuentas de dinero"
        value={tab}
        onChange={setTab}
        items={[
          {
            value: 'accounts',
            label: 'Cuentas',
            content: <AccountsTab onOpenMethods={() => setTab('methods')} />,
          },
          { value: 'methods', label: 'Métodos de pago', content: <MappingTab /> },
        ]}
      />
    </Page>
  );
};
