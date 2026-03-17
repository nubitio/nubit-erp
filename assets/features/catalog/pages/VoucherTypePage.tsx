import './VoucherTypePage.scss';
import { useMemo } from 'react';
import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { voucherTypeEvents } from '@features/voucherType';
import { VoucherTypeFields } from './VoucherTypeFields';
import { voucherTypeEndpoints } from '../api/catalogEndpoints';

export const VoucherTypePage = () => {
  const container = 'wrapper';
  const fields = useMemo(() => VoucherTypeFields(), []);

  const API_URL = voucherTypeEndpoints.list();

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
