import './PaymentMethodPage.scss';
import { useMemo } from 'react';
import { PaymentMethodFields } from './PaymentMethodFields';
import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { DxForm } from '@core/form/DxForm';
import { DxDialog } from '@core/dialog/DxDialog';
import { paymentMethodEvents } from '@features/paymentMethod';
import { paymentMethodEndpoints } from '../api/catalogEndpoints';

export const PaymentMethodPage = () => {
  const container = 'wrapper';
  const API_URL = paymentMethodEndpoints.list();
  const fields = useMemo(() => PaymentMethodFields(), []);
  return (
    <div id={container}>
      <DxDataGrid
        id={'payment-methods'}
        title={'Medios de pago'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={paymentMethodEvents}
      />
      <DxDialog events={paymentMethodEvents}>
        <DxForm url={API_URL} fields={fields} events={paymentMethodEvents} />
      </DxDialog>
    </div>
  );
};
