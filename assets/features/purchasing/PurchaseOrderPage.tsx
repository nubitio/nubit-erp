import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import {
  DataGridView,
  FormView,
  HydraAdapter,
  PageView,
  type DataRecord,
  type FormHandle,
  type GridHandle,
} from '@nubitio/crud';
import { useQuery } from '@tanstack/react-query';
import { SelectField } from '@nubitio/ui';
import { branchService } from '@features/branch';
import { hasPermission } from '@shared/permissions';
import { useSessionInfo } from '@shared/sessionInfo';
import { useEvents } from '@nubitio/core';
import toast from '@shared/lib/SharedUtils';
import { purchaseOrderEndpoints, withPurchasingBranch } from './api/purchaseOrderEndpoints';
import { PurchaseOrderFields, PurchaseOrderDetailFields } from './PurchaseOrderFields';
import purchaseOrderService from './services/purchaseOrderService';
import { purchaseOrderEvents } from './model/constants';

const PURCHASE_ORDER_FORM_LAYOUT = {
  type: 'sections' as const,
  sections: [
    {
      label: 'Datos principales',
      fields: ['supplier', 'warehouse', 'currency', 'expectedDate'],
    },
    {
      label: 'Notas',
      fields: ['note'],
    },
  ],
};

const purchasingAdapter = {
  ...HydraAdapter,
  buildItemUrl(baseUrl: string, id: string | number) {
    const [path, query] = baseUrl.split('?');
    const item = HydraAdapter.buildItemUrl(path, id);
    return query ? `${item}?${query}` : item;
  },
};

interface PurchaseOrderRow extends DataRecord {
  id: string;
  status: 'draft' | 'sent' | 'partially_received' | 'received' | 'cancelled';
}

export const PurchaseOrderPage = () => {
  const navigate = useNavigate();
  const gridRef = useRef<GridHandle>(null);
  const formRef = useRef<FormHandle>(null);
  const [orderFormOpen, setOrderFormOpen] = useState(false);
  const [on] = useEvents();

  const { currentBranchId } = useSessionInfo();
  const [search, setSearch] = useSearchParams();
  const canChooseBranch = hasPermission('purchases.cross_branch');
  const operatingBranches = useQuery({
    queryKey: ['purchasing', 'operating-branches', currentBranchId],
    queryFn: async () => (await branchService.getOperatingBranches()).data,
    enabled: canChooseBranch,
  });
  const options = operatingBranches.data?.filter((branch) => branch.id !== currentBranchId) ?? [];
  const requestedBranch = search.get('branchId') ?? '';
  const branchId =
    canChooseBranch && options.some((branch) => branch.id === requestedBranch)
      ? requestedBranch
      : '';
  const fields = useMemo(() => PurchaseOrderFields(branchId), [branchId]);
  const detailFields = useMemo(() => PurchaseOrderDetailFields(), []);

  useEffect(() => {
    const subscriptions = [
      on(purchaseOrderEvents.ADD, () => setOrderFormOpen(true)),
      on(purchaseOrderEvents.EDIT, () => setOrderFormOpen(true)),
      on(purchaseOrderEvents.CANCEL, () => setOrderFormOpen(false)),
      on(purchaseOrderEvents.SUCCESS, () => setOrderFormOpen(false)),
    ];

    return () => subscriptions.forEach((subscription) => subscription.unsubscribe());
  }, [on]);

  const API_URL = withPurchasingBranch(purchaseOrderEndpoints.list(), branchId);

  const sendOrder = (orderId: string) => {
    purchaseOrderService(branchId)
      .send(orderId)
      .then(() => {
        toast('Orden de compra enviada', 'success');
        gridRef.current?.refresh();
      })
      .catch(() => toast('No se pudo enviar la orden de compra', 'error'));
  };

  const cancelOrder = (orderId: string) => {
    purchaseOrderService(branchId)
      .cancel(orderId)
      .then(() => {
        toast('Orden de compra cancelada', 'success');
        gridRef.current?.refresh();
      })
      .catch(() => toast('No se pudo cancelar la orden de compra', 'error'));
  };

  const rowActions = (row: DataRecord) => {
    const order = row as PurchaseOrderRow;
    const actions = [];

    if (order.status === 'draft') {
      actions.push({
        text: 'Enviar',
        icon: 'ph-paper-plane-tilt',
        onClick: () => sendOrder(order.id),
      });
    }

    if (['draft', 'sent', 'partially_received'].includes(order.status)) {
      actions.push({
        text: 'Recibir mercadería',
        icon: 'ph-package',
        type: 'success' as const,
        onClick: () =>
          navigate(withPurchasingBranch(`/purchase-orders/${order.id}/receive`, branchId)),
      });
    }

    if (['draft', 'sent'].includes(order.status)) {
      actions.push({
        text: 'Cancelar',
        icon: 'ph-x-circle',
        type: 'danger' as const,
        onClick: () => cancelOrder(order.id),
      });
    }

    return actions;
  };

  return (
    <div id="wrapper" className={orderFormOpen ? 'wrapper--with-page' : undefined}>
      {!orderFormOpen && options.length > 0 && (
        <SelectField
          aria-label="Sucursal de compras"
          value={branchId}
          onChange={(event) =>
            setSearch(event.target.value ? { branchId: event.target.value } : {}, { replace: true })
          }
        >
          <option value="">Sucursal operativa</option>
          {options.map((branch) => (
            <option key={branch.id} value={branch.id}>
              {branch.name}
            </option>
          ))}
        </SelectField>
      )}
      {!orderFormOpen && (
        <DataGridView
          key={API_URL}
          id="purchase-orders"
          ref={gridRef}
          title="Órdenes de compra"
          url={API_URL}
          fields={fields}
          allowAdd
          adapter={purchasingAdapter}
          rowActions={rowActions}
          events={purchaseOrderEvents}
          filterRow={false}
          sort={[{ selector: 'expectedDate', desc: true }]}
          emptyState={{
            title: 'Sin órdenes de compra',
            description: 'Crea una orden de compra con el botón Nuevo.',
            icon: 'clipboard-text',
          }}
        />
      )}
      <PageView events={purchaseOrderEvents} onClose={() => setOrderFormOpen(false)}>
        <FormView
          ref={formRef}
          url={API_URL}
          fields={fields}
          detailFields={detailFields}
          detailPropertyName="writeItems"
          adapter={purchasingAdapter}
          formLayout={PURCHASE_ORDER_FORM_LAYOUT}
          events={purchaseOrderEvents}
          presentationContext={{
            presentationMode: 'page',
            hasMasterDetail: true,
            hasTabs: false,
          }}
        />
      </PageView>
    </div>
  );
};

PurchaseOrderPage.EVENTS = purchaseOrderEvents;
PurchaseOrderPage.API_URL = purchaseOrderEndpoints.list();
