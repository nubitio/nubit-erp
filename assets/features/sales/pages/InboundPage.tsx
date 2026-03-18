import './InboundPage.scss';
import type { RefObject } from 'react';
import { CrudPage, defineResource } from '@core/crud';
import type { DxFormEvents } from '@core/form/DxForm';
import { Constants } from '@core/common/Constants';
import { PurchasesDetailFields, PurchasesFields } from './InboundFields';
import { purchasesEvents } from '@features/purchases/model';

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
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
  onDetailRowsChanged: (formRef: RefObject<DxFormEvents | null>) => {
    const rows = formRef.current?.getDetailData() ?? [];
    const total = rows.reduce((sum: number, row: any) => sum + (row.total_amount ?? 0), 0);
    formRef.current?.setFieldValue('grand_total', Number(total));
  },
});

export const PurchasesPage = () => <CrudPage resource={purchasesResource} />;

// Backward-compatible alias while routes/menu migrate from /inbound to /purchases.
export const InboundPage = PurchasesPage;
