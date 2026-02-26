import './PaymentMethodPage.scss';
import { PaymentMethodFields } from './PaymentMethodFields';
import { Constants } from '@platform/common/Constants';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxForm } from '@platform/form/DxForm';
import { DxDialog } from '@platform/dialog/DxDialog';
import { paymentMethodEvents } from '@features/paymentMethod';

export const PaymentMethodPage = () => {
  const container = 'wrapper';
  const API_URL = Constants.API_BASE_URL + 'payment-methods';
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
