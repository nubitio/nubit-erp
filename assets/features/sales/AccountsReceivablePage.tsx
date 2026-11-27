import './AccountsReceivablePage.scss';
import { useCallback, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PageHeader, SelectField, Tabs } from '@nubitio/ui';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { hasPermission } from '@shared/permissions';
import { downloadBlob, filenameFromDisposition } from '@shared/lib/downloadBlob';
import { useSessionInfo } from '@shared/sessionInfo';
import toast from '@shared/lib/SharedUtils';
import { CollectPaymentDialog } from './CollectPaymentDialog';
import { CustomerAdvanceDialog } from './CustomerAdvanceDialog';
import {
  CustomerReceivableDetailView,
  type ReceivableCustomer,
} from './CustomerReceivableDetailView';
import { CustomerReceivablesGrid } from './CustomerReceivablesGrid';
import { ReceivableSummaryKpis } from './ReceivableSummaryKpis';
import { ReceivableInvoicesGrid } from './ReceivableInvoicesGrid';

const API = getCoreApiBaseUrl();

async function downloadCustomerStatement(id: number, branchId: string): Promise<void> {
  const params = new URLSearchParams({ customer: String(id), format: 'pdf' });
  if (branchId) params.set('branchId', branchId);
  const response = await httpClient.get<Blob>(`${API}sales/reports/customer-statement?${params}`, {
    responseType: 'blob',
  });
  downloadBlob(
    response.data,
    filenameFromDisposition(
      response.headers as Record<string, string> | undefined,
      `estado-cuenta-${id}.pdf`,
    ),
  );
}

/**
 * Cuentas por cobrar: a compact worklist with a focused, full-page customer
 * view. The customer context stays visible while reviewing open invoices.
 */
export const AccountsReceivablePage = () => {
  const [selectedCustomer, setSelectedCustomer] = useState<ReceivableCustomer | null>(null);
  const [collectCustomer, setCollectCustomer] = useState<ReceivableCustomer | null>(null);
  const [advanceCustomer, setAdvanceCustomer] = useState<ReceivableCustomer | null>(null);
  const [receivableView, setReceivableView] = useState<'invoices' | 'customers'>('customers');
  const [version, setVersion] = useState(0);
  const canCollect = hasPermission('sales.collect');

  const { currentBranchId } = useSessionInfo();
  const [requestedBranch, setRequestedBranch] = useState('');
  const branches = useQuery({
    queryKey: ['reporting-branches', currentBranchId],
    queryFn: async () =>
      (
        await httpClient.get<{ id: string; name: string; isActive: boolean }[]>(
          `${API}auth/reporting-branches`,
        )
      ).data,
  });
  const options = branches.data?.filter((branch) => branch.id !== currentBranchId) ?? [];
  const reportBranch = options.some((branch) => branch.id === requestedBranch)
    ? requestedBranch
    : '';
  const readOnly = Boolean(reportBranch);

  const refresh = useCallback(() => setVersion((current) => current + 1), []);

  const startCollection = useCallback((customer: ReceivableCustomer) => {
    setSelectedCustomer(null);
    setCollectCustomer(customer);
  }, []);

  return (
    <div id="wrapper" className="accounts-receivable-page">
      {selectedCustomer ? (
        <CustomerReceivableDetailView
          key={`${selectedCustomer.id}-${reportBranch}-${version}`}
          customer={selectedCustomer}
          branchId={reportBranch}
          onBack={() => setSelectedCustomer(null)}
          onCollect={() => setCollectCustomer(selectedCustomer)}
          onAdvance={() => setAdvanceCustomer(selectedCustomer)}
          onDownloadStatement={() => {
            downloadCustomerStatement(selectedCustomer.id, reportBranch).catch(() =>
              toast('No se pudo descargar el estado de cuenta. Inténtalo de nuevo.', 'error'),
            );
          }}
        />
      ) : (
        <>
          <PageHeader
            className="accounts-receivable-page__header"
            title="Cuentas por cobrar"
            subtitle="Seguimiento de comprobantes pendientes"
            actions={
              options.length > 0 ? (
                <SelectField
                  aria-label="Sucursal de cuentas por cobrar"
                  value={reportBranch}
                  onChange={(event) => {
                    setSelectedCustomer(null);
                    setCollectCustomer(null);
                    setAdvanceCustomer(null);
                    setRequestedBranch(event.target.value);
                  }}
                >
                  <option value="">Sucursal operativa</option>
                  {options.map((branch) => (
                    <option key={branch.id} value={branch.id}>
                      {branch.isActive ? branch.name : `${branch.name} (inactiva)`}
                    </option>
                  ))}
                </SelectField>
              ) : null
            }
          />
          <ReceivableSummaryKpis refreshKey={version} branchId={reportBranch} />
          <Tabs
            className="accounts-receivable-page__tabs"
            aria-label="Vista de cuentas por cobrar"
            value={receivableView}
            onChange={setReceivableView}
            items={[
              {
                value: 'customers',
                label: 'Por cliente',
                content: (
                  <CustomerReceivablesGrid
                    key={reportBranch}
                    branchId={reportBranch}
                    refreshKey={version}
                    onOpenDetail={setSelectedCustomer}
                    onCollect={startCollection}
                  />
                ),
              },
              {
                value: 'invoices',
                label: 'Por comprobante',
                content: <ReceivableInvoicesGrid branchId={reportBranch} onCollected={refresh} />,
              },
            ]}
          />
        </>
      )}
      {collectCustomer && canCollect && !readOnly && (
        <CollectPaymentDialog
          key={`collect-${collectCustomer.id}-${version}`}
          customerId={collectCustomer.id}
          customerName={collectCustomer.name}
          currency={collectCustomer.currency}
          balanceDue={collectCustomer.totalBalance}
          onClose={() => setCollectCustomer(null)}
          onDone={() => {
            setCollectCustomer(null);
            setSelectedCustomer(null);
            refresh();
          }}
        />
      )}
      {advanceCustomer && canCollect && !readOnly && (
        <CustomerAdvanceDialog
          key={`advance-${advanceCustomer.id}-${version}`}
          customerId={advanceCustomer.id}
          customerName={advanceCustomer.name}
          currency={advanceCustomer.currency}
          onClose={() => setAdvanceCustomer(null)}
          onDone={() => {
            setAdvanceCustomer(null);
            setSelectedCustomer(null);
            refresh();
          }}
        />
      )}
    </div>
  );
};
