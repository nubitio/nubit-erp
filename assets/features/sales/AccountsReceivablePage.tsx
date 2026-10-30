import './AccountsReceivablePage.scss';
import { useCallback, useState } from 'react';
import { PageHeader } from '@nubitio/ui';
import { FinanceNavigation } from '@shared/ui/finance/FinanceNavigation';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { hasPermission } from '@shared/permissions';
import { CollectPaymentDialog } from './CollectPaymentDialog';
import { CustomerAdvanceDialog } from './CustomerAdvanceDialog';
import { CustomerReceivableDrawer, type ReceivableCustomer } from './CustomerReceivableDrawer';
import { CustomerReceivablesGrid } from './CustomerReceivablesGrid';
import { ReceivableSummaryKpis } from './ReceivableSummaryKpis';

const API = getCoreApiBaseUrl();

async function downloadCustomerStatement(id: number): Promise<void> {
  const response = await httpClient.get<Blob>(
    `${API}sales/reports/customer-statement?customer=${id}&format=pdf`,
    { responseType: 'blob' },
  );
  const disposition = response.headers['content-disposition'] as string | undefined;
  const match = disposition ? /filename="?([^";]+)"?/i.exec(disposition) : null;
  const objectUrl = URL.createObjectURL(response.data);
  const anchor = document.createElement('a');
  anchor.href = objectUrl;
  anchor.download = match?.[1] ?? `estado-cuenta-${id}.pdf`;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(objectUrl);
}

/**
 * Cuentas por cobrar, SMB-first: one list of debtors, one collection flow and a
 * per-customer drawer. The old Deudores/Comprobantes tabs and the per-invoice
 * collect + advance flows were collapsed into this screen (#409 redesign).
 */
export const AccountsReceivablePage = () => {
  const [selectedCustomer, setSelectedCustomer] = useState<ReceivableCustomer | null>(null);
  const [collectCustomer, setCollectCustomer] = useState<ReceivableCustomer | null>(null);
  const [advanceCustomer, setAdvanceCustomer] = useState<ReceivableCustomer | null>(null);
  const [version, setVersion] = useState(0);
  const canCollect = hasPermission('sales.collect');

  const refresh = useCallback(() => setVersion((current) => current + 1), []);

  const startCollection = useCallback((customer: ReceivableCustomer) => {
    setSelectedCustomer(null);
    setCollectCustomer(customer);
  }, []);

  return (
    <div id="wrapper" className="accounts-receivable-page">
      <PageHeader
        title="Cuentas por cobrar"
        subtitle="Saldo pendiente de tus clientes y registro de pagos."
      />
      <FinanceNavigation />
      <ReceivableSummaryKpis refreshKey={version} />
      <CustomerReceivablesGrid
        refreshKey={version}
        onOpenDetail={setSelectedCustomer}
        onCollect={startCollection}
      />
      <CustomerReceivableDrawer
        customer={selectedCustomer}
        refreshKey={version}
        onClose={() => setSelectedCustomer(null)}
        onCollect={startCollection}
        onAdvance={(customer) => {
          setSelectedCustomer(null);
          setAdvanceCustomer(customer);
        }}
        onDownloadStatement={(customerId) => {
          void downloadCustomerStatement(customerId);
        }}
      />
      {collectCustomer && canCollect && (
        <CollectPaymentDialog
          key={`collect-${collectCustomer.id}-${version}`}
          customerId={collectCustomer.id}
          customerName={collectCustomer.name}
          currency={collectCustomer.currency}
          balanceDue={collectCustomer.totalBalance}
          onClose={() => setCollectCustomer(null)}
          onDone={() => {
            setCollectCustomer(null);
            refresh();
          }}
        />
      )}
      {advanceCustomer && canCollect && (
        <CustomerAdvanceDialog
          key={`advance-${advanceCustomer.id}-${version}`}
          customerId={advanceCustomer.id}
          customerName={advanceCustomer.name}
          currency={advanceCustomer.currency}
          onClose={() => setAdvanceCustomer(null)}
          onDone={() => {
            setAdvanceCustomer(null);
            refresh();
          }}
        />
      )}
    </div>
  );
};
