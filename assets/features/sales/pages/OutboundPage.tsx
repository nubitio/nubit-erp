import './OutboundPage.scss';
import { useRef } from 'react';
import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm, type DxFormEvents } from '@core/form/DxForm';
import { Constants } from '@core/common/Constants';
import { OutboundDetailFields, OutboundFields } from './OutboundFields';
import { useEvents } from '@core/event/EventHook';
import { outboundEvents, useOutboundFormObservers } from '@features/outbound';

const API_URL = Constants.API_BASE_URL + 'movements';
const fields = OutboundFields();
const detailFields = OutboundDetailFields();

export const OutboundPage = () => {
  const [on] = useEvents();
  const formRef = useRef<DxFormEvents>(null);

  useOutboundFormObservers({ on, apiUrl: API_URL, editEventName: outboundEvents.EDIT, formRef });

  return (
    <div id="wrapper">
      <DxDataGrid
        id="outbound"
        title="Salidas"
        url={API_URL + '?type=outbound'}
        detailUrl={API_URL + '/{id}/items'}
        allowAdd
        allowEdit
        allowDelete
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
