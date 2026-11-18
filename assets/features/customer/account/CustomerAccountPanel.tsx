import { useCallback, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { downloadBlob, filenameFromDisposition } from '@shared/lib/downloadBlob';
import toast from '@shared/lib/SharedUtils';
import { hasPermission } from '@shared/permissions';
import { CollectPaymentDialog } from '../../sales/CollectPaymentDialog';
import { CustomerAdvanceDialog } from '../../sales/CustomerAdvanceDialog';
import {
  CustomerReceivableDrawer,
  type ReceivableCustomer,
} from '../../sales/CustomerReceivableDrawer';
import { listOpenInvoices } from '../../sales/services/receivableService';
import type { Customer } from '../form/customerForm';
import { openBalance } from './openBalance';

const API = getCoreApiBaseUrl();

async function downloadStatement(customerId: number): Promise<void> {
  try {
    const response = await httpClient.get<Blob>(
      `${API}sales/reports/customer-statement?customer=${customerId}&format=pdf`,
      { responseType: 'blob' },
    );
    downloadBlob(
      response.data,
      filenameFromDisposition(
        response.headers as Record<string, string> | undefined,
        `estado-cuenta-${customerId}.pdf`,
      ),
    );
  } catch {
    toast('No se pudo descargar el estado de cuenta', 'error');
  }
}

interface Props {
  /** The customer whose account is shown; `null` keeps everything closed. */
  customer: Customer | null;
  onClose: () => void;
}

/**
 * A customer's account from the Customers screen: open balance and invoices, recent payments,
 * collection, advance and statement. It reuses the receivables drawer and dialogs instead of
 * copying them, so both screens stay one behaviour. The drawer opens once the balance is known,
 * never showing a made-up zero.
 */
export const CustomerAccountPanel = ({ customer, onClose }: Props) => {
  const queryClient = useQueryClient();
  const [collect, setCollect] = useState<ReceivableCustomer | null>(null);
  const [advance, setAdvance] = useState<ReceivableCustomer | null>(null);
  const canCollect = hasPermission('sales.collect');

  const invoices = useQuery({
    queryKey: ['customer', 'open-invoices', customer?.id],
    queryFn: ({ signal }) => listOpenInvoices(customer!.id, signal),
    enabled: customer !== null,
    staleTime: 0,
  });

  const refresh = useCallback(
    () => queryClient.invalidateQueries({ queryKey: ['customer', 'open-invoices'] }),
    [queryClient],
  );

  if (customer !== null && invoices.isError) {
    toast('No se pudo cargar la cuenta del cliente', 'error');
    onClose();
  }

  const balance = invoices.data ? openBalance(invoices.data) : null;
  const shown: ReceivableCustomer | null =
    customer && balance
      ? {
          id: customer.id,
          name: customer.businessName,
          totalBalance: balance.total,
          currency: balance.currency,
        }
      : null;

  return (
    <>
      <CustomerReceivableDrawer
        customer={collect || advance ? null : shown}
        onClose={onClose}
        onCollect={setCollect}
        onAdvance={setAdvance}
        onDownloadStatement={(id) => void downloadStatement(id)}
      />
      {collect && canCollect && (
        <CollectPaymentDialog
          customerId={collect.id}
          customerName={collect.name}
          currency={collect.currency}
          balanceDue={collect.totalBalance}
          onClose={() => setCollect(null)}
          onDone={() => {
            setCollect(null);
            void refresh();
          }}
        />
      )}
      {advance && canCollect && (
        <CustomerAdvanceDialog
          customerId={advance.id}
          customerName={advance.name}
          currency={advance.currency}
          onClose={() => setAdvance(null)}
          onDone={() => {
            setAdvance(null);
            void refresh();
          }}
        />
      )}
    </>
  );
};
