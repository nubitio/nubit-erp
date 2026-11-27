import { useMemo, useRef, useState } from 'react';
import {
  DataGridView,
  HydraAdapter,
  currencyField,
  dateField,
  identityField,
  textField,
  type DataRecord,
  type GridCellContext,
  type GridHandle,
} from '@nubitio/crud';
import { Button } from '@nubitio/ui';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { moneyCell } from '@shared/ui/crudFormatters';
import { hasPermission } from '@shared/permissions';
import { formatInvoiceNumber } from '@shared/lib/displayFormat';
import { SaleCollectPaymentDialog } from './detail/SaleCollectPaymentDialog';
import type { SaleDetail } from './detail/saleDetailModel';
import { ReceivableGridFrame } from './ReceivableGridFrame';

const API = getCoreApiBaseUrl();

interface InvoiceRecord extends DataRecord {
  id: string;
  customerId?: number | string;
  customerName?: string;
  invoiceFullNumber?: string | null;
  dueDate?: string | null;
  balanceDue?: string;
  grandTotal?: string;
  subtotal?: string;
  discountTotal?: string;
  taxTotal?: string;
  paidTotal?: string;
  changeTotal?: string;
  status?: string;
  currency?: string;
}

export function ReceivableInvoicesGrid({
  branchId,
  onCollected,
}: {
  branchId?: string;
  onCollected?: () => void;
}) {
  const gridRef = useRef<GridHandle>(null);
  const [collectSale, setCollectSale] = useState<SaleDetail | null>(null);
  const fields = useMemo(
    () => [
      identityField().visible(false).visibleOnForm(false).build(),
      textField()
        .name('invoiceFullNumber')
        .label('Comprobante')
        .cardRole('title')
        .formatter(
          (cell: GridCellContext) =>
            formatInvoiceNumber(cell.value as string | null | undefined) || 'Saldo inicial',
        )
        .build(),
      textField()
        .name('customerName')
        .label('Cliente')
        .formatter((cell: GridCellContext) => String(cell.value ?? 'Cliente'))
        .build(),
      dateField().name('dueDate').label('Vencimiento').build(),
      currencyField().name('grandTotal').label('Total').align('right').formatter(moneyCell).build(),
      currencyField()
        .name('balanceDue')
        .label('Saldo')
        .cardRole('primary')
        .align('right')
        .formatter(moneyCell)
        .build(),
      textField()
        .name('_collect')
        .label('Acciones')
        .hideable(false)
        .width(76)
        .align('right')
        .filterable(false)
        .sortable(false)
        .formatter((cell: GridCellContext) => {
          const row = cell.data as InvoiceRecord | undefined;
          const customerId = Number(row?.customerId);
          if (!row || !Number.isFinite(customerId) || branchId || !hasPermission('sales.collect'))
            return null;
          return (
            <span className="owner-row-buttons" onClick={(event) => event.stopPropagation()}>
              <Button
                size="sm"
                variant="secondary"
                onClick={() =>
                  setCollectSale({
                    id: row.id,
                    invoiceFullNumber: row.invoiceFullNumber,
                    status: row.status ?? 'CONFIRMED',
                    currency: row.currency ?? 'PEN',
                    subtotal: row.subtotal ?? row.grandTotal ?? '0',
                    discountTotal: row.discountTotal ?? '0',
                    taxTotal: row.taxTotal ?? '0',
                    grandTotal: row.grandTotal ?? row.balanceDue ?? '0',
                    paidTotal: row.paidTotal ?? '0',
                    changeTotal: row.changeTotal ?? '0',
                    balanceDue: row.balanceDue ?? '0',
                    customer: { id: customerId, businessName: row.customerName },
                  })
                }
              >
                Cobrar
              </Button>
            </span>
          );
        })
        .build(),
    ],
    [branchId],
  );

  const query = new URLSearchParams();
  if (branchId) query.set('branchId', branchId);
  const url = `${API}sales/reports/receivable-invoices${query.size ? `?${query}` : ''}`;

  return (
    <>
      <ReceivableGridFrame gridRef={gridRef} className="accounts-receivable-page__invoice-grid">
        <DataGridView
          id="accounts-receivable-invoices"
          ref={gridRef}
          title="Comprobantes pendientes"
          url={url}
          fields={fields}
          adapter={HydraAdapter}
          appearance="page"
          toolbarVisible={false}
          sort={[{ selector: 'dueDate', desc: false }]}
          allowAdd={false}
          allowEdit={false}
          allowDelete={false}
          emptyState={{
            title: 'No hay comprobantes pendientes',
            description: 'Los comprobantes con saldo por cobrar aparecerán aquí.',
            icon: 'receipt',
          }}
        />
      </ReceivableGridFrame>
      {collectSale && (
        <SaleCollectPaymentDialog
          sale={collectSale}
          onClose={() => setCollectSale(null)}
          onDone={() => {
            setCollectSale(null);
            gridRef.current?.refresh();
            onCollected?.();
          }}
        />
      )}
    </>
  );
}
