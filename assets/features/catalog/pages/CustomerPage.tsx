import './CustomerPage.scss';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm, DxFormEvents } from '@platform/form/DxForm';
import { customerApiUrl, customerEvents } from '@features/customer';
import { CustomerFields } from './CustomerFields';
import { useRef } from 'react';

export const CustomerPage = () => {
  const formRef = useRef<DxFormEvents | null>(null);
  const container = 'wrapper';
  const fields = CustomerFields(formRef);

  return (
    <div id={container}>
      <DxDataGrid
        id={'customer'}
        title={'Clientes'}
        url={customerApiUrl}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={customerEvents}
      />
      <DxDialog events={customerEvents}>
        <DxForm ref={formRef} url={customerApiUrl} fields={fields} events={customerEvents} />
      </DxDialog>
    </div>
  );
};

CustomerPage.EVENTS = customerEvents;
CustomerPage.API_URL = customerApiUrl;
