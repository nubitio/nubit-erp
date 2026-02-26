import './VoucherTypePage.scss';
import { Constants } from '@platform/common/Constants';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm } from '@platform/form/DxForm';
import { voucherTypeEvents } from '@features/voucherType';
import { VoucherTypeFields } from './VoucherTypeFields';

export const VoucherTypePage = () => {
  const container = 'wrapper';
  const fields = VoucherTypeFields();

  const API_URL = Constants.API_BASE_URL + 'voucher-types';

  return (
    <div id={container}>
      <DxDataGrid
        id={'voucherType'}
        title={'Tipos de comprobante'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={voucherTypeEvents}
      />
      <DxDialog events={voucherTypeEvents}>
        <DxForm url={API_URL} fields={fields} events={voucherTypeEvents} />
      </DxDialog>
    </div>
  );
};
