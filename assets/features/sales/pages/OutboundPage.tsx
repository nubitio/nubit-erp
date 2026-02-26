import './OutboundPage.scss';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm, DxFormEvents } from '@platform/form/DxForm';
import { Constants } from '@platform/common/Constants';
import { OutboundDetailFields, OutboundFields } from './OutboundFields';
import { useRef } from 'react';
import { useEvents } from '@platform/event/EventHook';
import { outboundEvents, useOutboundFormObservers } from '@features/outbound';

export const OutboundPage = () => {
  const [on] = useEvents();
  const container = 'wrapper';
  const fields = OutboundFields();
  const detailFields = OutboundDetailFields();
  const formRef = useRef<DxFormEvents>(null);

  const API_URL = Constants.API_BASE_URL + 'movements';

  useOutboundFormObservers({
    on,
    apiUrl: API_URL,
    editEventName: outboundEvents.EDIT,
    formRef,
  });

  return (
    <div id={container}>
      <DxDataGrid
        id={'outbound'}
        title={'Salidas'}
        url={API_URL + '?type=outbound'}
        detailUrl={API_URL + '/{id}/items'}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        detailFields={detailFields}
        events={outboundEvents}
      />
      <DxDialog width={800} height={450} events={outboundEvents}>
        <DxForm
          ref={formRef}
          url={API_URL}
          fields={fields}
          detailFields={detailFields}
          events={outboundEvents}
        />
      </DxDialog>
    </div>
  );
};
