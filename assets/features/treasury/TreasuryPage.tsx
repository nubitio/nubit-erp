import { EmptyState, Page, PageHeader, Skeleton, Tabs } from '@nubitio/ui';
import { useState } from 'react';
import { useTenantRuntimeConfig } from '@shared/hooks/useTenantRuntimeConfig';
import { AccountsTab } from './AccountsTab';
import { MappingTab } from './MappingTab';
import './TreasuryPage.scss';

type TabKey = 'accounts' | 'methods';

/**
 * Cuentas de dinero (ADR-021, Treasury): where money sits, how much, and which account each payment method
 * lands in. Cash drawers appear here with the balance of their open session; their movements stay in Caja.
 */
export const TreasuryPage = () => {
  const [tab, setTab] = useState<TabKey>('accounts');
  const config = useTenantRuntimeConfig();

  if (config.isPending) return <Skeleton height={240} />;
  if (config.data?.capabilities['treasury.accounts']?.enabled !== true) {
    return (
      <Page>
        <EmptyState
          icon="bank"
          title="Cuentas de dinero no está habilitado"
          description="Pide al administrador que active esta función para tu negocio."
        />
      </Page>
    );
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
