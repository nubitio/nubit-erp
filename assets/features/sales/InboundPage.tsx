/* eslint-disable react-hooks/refs -- CRUD detail callbacks intentionally read Form refs after detail edits, not during render. */
import type { RefObject } from 'react';
import { useMemo } from 'react';
import { SchemaCrudPage, defineResource, type FormHandle } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { PurchasesDetailFields, PurchasesFields } from './InboundFields';

const API = getCoreApiBaseUrl();
import { useSessionInfo } from '@shared/sessionInfo';
import { useTenantRuntimeConfig } from '@shared/hooks';

type PurchaseDetailRow = {
  totalAmount?: number;
};

export const PurchasesPage = () => {
  const { currentBranchId } = useSessionInfo();
  const { data: runtimeConfig } = useTenantRuntimeConfig();

  const resource = useMemo(() => {
    const detailFields = PurchasesDetailFields();
    const detailUrl = API + 'purchases/{id}/items';

    const showWarehouseSelector = runtimeConfig?.ui.inventory.showWarehouseSelector ?? true;
    const showWarehouseColumn = showWarehouseSelector || !runtimeConfig?.defaults.warehouse?.id;

    const basicColumns = showWarehouseColumn
      ? ['issuedAt', 'supplier', 'warehouse', 'grandTotal']
      : ['issuedAt', 'supplier', 'grandTotal'];
    const fullColumns = showWarehouseColumn
      ? ['issuedAt', 'supplier', 'warehouse', 'note', 'grandTotal']
      : ['issuedAt', 'supplier', 'note', 'grandTotal'];
    const formFields = showWarehouseColumn
      ? [
          'issuedAt',
          'warehouse',
          'supplier',
          'supplierDocType',
          'supplierDocSeries',
          'supplierDocNumber',
          'grandTotal',
        ]
      : [
          'issuedAt',
          'supplier',
          'supplierDocType',
          'supplierDocSeries',
          'supplierDocNumber',
          'grandTotal',
        ];

    return defineResource(API + 'purchases', {
      id: 'purchases',
      title: 'Recepción de mercadería',
      gridAppearance: 'page',
      filterRow: true,
      filterToolbarFields: ['issuedAt', 'supplier'],
      fields: PurchasesFields(currentBranchId, runtimeConfig),
      gridDetail: { url: detailUrl, fields: detailFields },
      formDetail: {
        propertyName: 'items',
        fields: detailFields,
        allowAdding: true,
        allowDeleting: true,
        allowUpdating: true,
        required: true,
      },
      sort: [{ selector: 'createdAt', desc: true }],
      viewMode: 'page',
      routing: { routeParam: 'id' },
      columnPresets: [
        {
          key: 'basic',
          label: 'Básico',
          columns: basicColumns,
        },
        {
          key: 'full',
          label: 'Completo',
          columns: fullColumns,
        },
      ],
      defaultPreset: 'basic',
      emptyState: {
        title: 'Sin compras registradas',
        description:
          'Ingresa mercadería de un proveedor para que el stock y el costo se actualicen.',
        icon: 'shopping-cart',
      },
      formLayout: {
        type: 'tabs',
        tabs: [
          {
            label: 'Cabecera',
            icon: 'ph-receipt',
            fields: formFields,
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
  }, [currentBranchId, runtimeConfig]);

  return <SchemaCrudPage resource={resource} />;
};

// Route alias: /inbound redirects to the same page as /purchases.
export const InboundPage = PurchasesPage;
