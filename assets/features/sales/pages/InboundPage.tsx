import './InboundPage.scss';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm, DxFormEvents } from '@platform/form/DxForm';
import { Constants } from '@platform/common/Constants';
import { PurchasesDetailFields, PurchasesFields } from './InboundFields';
import { useRef } from 'react';
import { useEvents } from '@platform/event/EventHook';
import { purchasesEvents, usePurchasesFormObservers } from '@features/purchases/model';

export const PurchasesPage = () => {
  const [on] = useEvents();
  const formRef = useRef<DxFormEvents>(null);
  const container = 'wrapper';
  const fields = PurchasesFields();
  const detailFields = PurchasesDetailFields();

  const API_URL = Constants.API_BASE_URL + 'movements';

  const setTotal = () => {
    const rows = formRef.current?.getDetailData() ?? [];
    let total = 0;
    rows.forEach((row: any) => {
      total += row.precio_total;
    });

    formRef.current?.setFieldValue('total', Number(total));
  };

  usePurchasesFormObservers({
    on,
    apiUrl: API_URL,
    editEventName: purchasesEvents.EDIT,
    formRef,
    onDetailRowsChanged: setTotal,
  });

  return (
    <div id={container}>
      <DxDataGrid
        id={'purchases'}
        title={'Compras'}
        url={API_URL + '?type=purchases'}
        detailUrl={API_URL + '/{id}/items'}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        detailFields={detailFields}
        events={purchasesEvents}
        sort={[{ selector: 'date_created', desc: true }]}
      />
      <DxDialog width={900} height={600} events={purchasesEvents}>
        <DxForm
          ref={formRef}
          url={API_URL}
          fields={fields}
          detailFields={detailFields}
          events={purchasesEvents}
        />
      </DxDialog>
    </div>
  );
};

// Backward-compatible alias while routes/menu migrate from /inbound to /purchases.
export const InboundPage = PurchasesPage;
