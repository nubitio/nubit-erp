/* eslint-disable react-hooks/refs -- CRUD detail callbacks intentionally read Form refs after detail edits, not during render. */
import type { RefObject } from 'react';
import { useMemo } from 'react';
import { SmartCrudPage, defineResource, type FormHandle } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { PurchasesDetailFields, PurchasesFields } from './InboundFields';

const API = getCoreApiBaseUrl();
import { useSessionInfo } from '@shared/sessionInfo';

type PurchaseDetailRow = {
  totalAmount?: number;
};

export const PurchasesPage = () => {
  const { currentBranchId } = useSessionInfo();

  const resource = useMemo(() => {
    const detailFields = PurchasesDetailFields();
    const detailUrl = API + 'purchases/{id}/items';

    return defineResource(API + 'purchases', {
      id: 'purchases',
      title: 'Compras',
      fields: PurchasesFields(currentBranchId),
      gridDetail: { url: detailUrl, fields: detailFields },
      formDetail: { url: detailUrl, fields: detailFields },
      sort: [{ selector: 'createdAt', desc: true }],
      viewMode: 'page',
      routing: { routeParam: 'id' },
      columnPresets: [
        {
          key: 'basic',
          label: 'Básico',
          columns: ['issuedAt', 'supplier', 'warehouse', 'grandTotal'],
        },
        {
          key: 'full',
          label: 'Completo',
          columns: ['issuedAt', 'supplier', 'warehouse', 'note', 'grandTotal'],
        },
      ],
      defaultPreset: 'basic',
      emptyState: {
        title: 'Sin compras registradas',
        description: 'Registra tu primera compra con el botón Nuevo.',
        icon: 'shopping-cart',
      },
      formLayout: {
        type: 'tabs',
        tabs: [
          {
            label: 'Cabecera',
            icon: 'ph-receipt',
            fields: ['issuedAt', 'warehouse', 'supplier', 'grandTotal'],
          },
          {
            label: 'Notas',
            icon: 'ph-note',
            fields: ['note'],
          },
        ],
      },
      onDetailRowsChanged: (formRef: RefObject<FormHandle | null>) => {
        const rows = formRef.current?.getDetailData() ?? [];
        const total = rows.reduce(
          (sum: number, row: PurchaseDetailRow) => sum + (row.totalAmount ?? 0),
          0,
        );
        formRef.current?.setFieldValue('grandTotal', Number(total));
      },
    });
  }, [currentBranchId]);

  return <SmartCrudPage resource={resource} />;
};

// Route alias: /inbound redirects to the same page as /purchases.
export const InboundPage = PurchasesPage;
