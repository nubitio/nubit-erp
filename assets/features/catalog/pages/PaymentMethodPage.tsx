import './PaymentMethodPage.scss';
import { PaymentMethodFields } from './PaymentMethodFields';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxForm } from '@platform/form/DxForm';
import { DxDialog } from '@platform/dialog/DxDialog';
import { paymentMethodEvents } from '@features/paymentMethod';
import { paymentMethodEndpoints } from '../api/catalogEndpoints';

export const PaymentMethodPage = () => {
  const container = 'wrapper';
  const API_URL = paymentMethodEndpoints.list();
  const fields = PaymentMethodFields();
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
