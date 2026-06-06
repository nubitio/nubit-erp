import type { RefObject } from 'react';
import { useMemo } from 'react';
import { SmartCrudPage, defineResource, type FormHandle } from '@nubit/crud';
import { getCoreApiBaseUrl } from '@nubit/core';
import { PurchasesDetailFields, PurchasesFields } from './InboundFields';

const API = getCoreApiBaseUrl();
import { useSessionInfo } from '@app/contexts/useSessionInfo';

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
      viewMode: { mode: 'drawer', drawerWidth: 960 },
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
      onDetailRowsChanged: (formRef: RefObject<FormHandle | null>) => {
        const rows = formRef.current?.getDetailData() ?? [];
        const total = rows.reduce((sum: number, row: any) => sum + (row.totalAmount ?? 0), 0);
        formRef.current?.setFieldValue('grandTotal', Number(total));
      },
    });
  }, [currentBranchId]);

  return <SmartCrudPage resource={resource} />;
};

// Route alias: /inbound redirects to the same page as /purchases.
export const InboundPage = PurchasesPage;
