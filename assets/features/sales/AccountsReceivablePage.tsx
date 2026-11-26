import './AccountsReceivablePage.scss';
import { useCallback, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PageHeader, SelectField } from '@nubitio/ui';
import { FinanceNavigation } from '@shared/ui/finance/FinanceNavigation';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { hasPermission } from '@shared/permissions';
import { downloadBlob, filenameFromDisposition } from '@shared/lib/downloadBlob';
import { useSessionInfo } from '@shared/sessionInfo';
import toast from '@shared/lib/SharedUtils';
import { CollectPaymentDialog } from './CollectPaymentDialog';
import { CustomerAdvanceDialog } from './CustomerAdvanceDialog';
import { CustomerReceivableDrawer, type ReceivableCustomer } from './CustomerReceivableDrawer';
import { CustomerReceivablesGrid } from './CustomerReceivablesGrid';
import { ReceivableSummaryKpis } from './ReceivableSummaryKpis';

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
      <PageHeader
        title="Cuentas por cobrar"
        subtitle="Saldo pendiente de tus clientes y registro de pagos."
      />
      <FinanceNavigation />
      {options.length > 0 && (
        <SelectField
          aria-label="Sucursales autorizadas para cuentas por cobrar"
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
      )}
      <ReceivableSummaryKpis refreshKey={version} branchId={reportBranch} />
      <CustomerReceivablesGrid
        key={reportBranch}
        branchId={reportBranch}
        refreshKey={version}
        onOpenDetail={setSelectedCustomer}
        onCollect={startCollection}
      />
      <CustomerReceivableDrawer
        customer={selectedCustomer}
        branchId={reportBranch}
        refreshKey={version}
        onClose={() => setSelectedCustomer(null)}
        onCollect={startCollection}
        onAdvance={(customer) => {
          setSelectedCustomer(null);
          setAdvanceCustomer(customer);
        }}
        onDownloadStatement={(customerId) => {
          downloadCustomerStatement(customerId, reportBranch).catch(() =>
            toast('No se pudo descargar el estado de cuenta. Inténtalo de nuevo.', 'error'),
          );
        }}
      />
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
            refresh();
          }}
        />
      )}
    </div>
  );
};
