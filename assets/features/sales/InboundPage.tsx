import type { RefObject } from 'react';
import { CrudPage, defineResource, type FormHandle } from '@nubit/core';
import { Constants } from '@core/common/Constants';
import { PurchasesDetailFields, PurchasesFields } from './InboundFields';
import { purchasesEvents } from '@features/purchases/model/events';

const purchasesResource = defineResource({
  id: 'purchases',
  title: 'Compras',
  apiUrl: Constants.API_BASE_URL + 'purchases',
  fields: PurchasesFields(),
  detailFields: PurchasesDetailFields(),
  detailUrl: Constants.API_BASE_URL + 'purchases/{id}/items',
  events: purchasesEvents,
  sort: [{ selector: 'createdAt', desc: true }],
  dialogWidth: 900,
  dialogHeight: 600,
  onDetailRowsChanged: (formRef: RefObject<FormHandle | null>) => {
    const rows = formRef.current?.getDetailData() ?? [];
    const total = rows.reduce((sum: number, row: any) => sum + (row.totalAmount ?? 0), 0);
    formRef.current?.setFieldValue('grandTotal', Number(total));
  },
});

export const PurchasesPage = () => <CrudPage resource={purchasesResource} />;

// Route alias: /inbound redirects to the same page as /purchases.
export const InboundPage = PurchasesPage;
