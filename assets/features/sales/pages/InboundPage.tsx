import './InboundPage.scss';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm, DxFormEvents } from '@platform/form/DxForm';
import { Constants } from '@platform/common/Constants';
import { InboundDetailFields, InboundFields } from './InboundFields';
import { useRef } from 'react';
import { useEvents } from '@platform/event/EventHook';
import { inboundEvents, useInboundFormObservers } from '@features/inbound';

export const InboundPage = () => {
  const [on] = useEvents();
  const formRef = useRef<DxFormEvents>(null);
  const container = 'wrapper';
  const fields = InboundFields();
  const detailFields = InboundDetailFields();

  const API_URL = Constants.API_BASE_URL + 'movements';

  const setTotal = () => {
    const rows = formRef.current?.getDetailData() ?? [];
    let total = 0;
    rows.forEach((row: any) => {
      total += row.precio_total;
    });

    formRef.current?.setFieldValue('total', Number(total));
  };

  useInboundFormObservers({
    on,
    apiUrl: API_URL,
    editEventName: inboundEvents.EDIT,
    formRef,
    onDetailRowsChanged: setTotal,
  });

  return (
    <div id={container}>
      <DxDataGrid
        id={'inbound'}
        title={'Ingresos'}
        url={API_URL + '?type=inbound'}
        detailUrl={API_URL + '/{id}/items'}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        detailFields={detailFields}
        events={inboundEvents}
        sort={[{ selector: 'date_created', desc: true }]}
      />
      <DxDialog width={900} height={600} events={inboundEvents}>
        <DxForm
          ref={formRef}
          url={API_URL}
          fields={fields}
          detailFields={detailFields}
          events={inboundEvents}
        />
      </DxDialog>
    </div>
  );
};
