import type { RefObject } from 'react';
import { useMemo } from 'react';
import { CrudPage, defineResource, type FormHandle } from '@nubit/core';
import { API_BASE_URL } from '@app/constants';
import { PurchasesDetailFields, PurchasesFields } from './InboundFields';
import { purchasesEvents } from '@features/purchases/model/events';
import { useSessionInfo } from '@app/contexts/useSessionInfo';

export const PurchasesPage = () => {
  const { currentBranchId } = useSessionInfo();

  const resource = useMemo(() => defineResource({
    id: 'purchases',
    title: 'Compras',
    apiUrl: API_BASE_URL + 'purchases',
    fields: PurchasesFields(currentBranchId),
    detailFields: PurchasesDetailFields(),
    detailUrl: API_BASE_URL + 'purchases/{id}/items',
    events: purchasesEvents,
    sort: [{ selector: 'createdAt', desc: true }],
    dialogWidth: 900,
    dialogHeight: 600,
    onDetailRowsChanged: (formRef: RefObject<FormHandle | null>) => {
      const rows = formRef.current?.getDetailData() ?? [];
      const total = rows.reduce((sum: number, row: any) => sum + (row.totalAmount ?? 0), 0);
      formRef.current?.setFieldValue('grandTotal', Number(total));
    },
  }), [currentBranchId]);

  return <CrudPage resource={resource} />;
};

// Route alias: /inbound redirects to the same page as /purchases.
export const InboundPage = PurchasesPage;
