import './SupplierPage.scss';
import { Constants } from '@platform/common/Constants';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm, DxFormEvents } from '@platform/form/DxForm';
import { supplierEvents } from '@features/supplier';
import { SupplierFields } from './SupplierFields';
import { useRef } from 'react';

export const SupplierPage = () => {
  const container = 'wrapper';
  const formRef = useRef<DxFormEvents | null>(null);
  const fields = SupplierFields(formRef.current);

  const API_URL = Constants.API_BASE_URL + 'suppliers';

  return (
    <div id={container}>
      <DxDataGrid
        id={'supplier'}
        title={'Proveedores'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={supplierEvents}
      />
      <DxDialog events={supplierEvents}>
        <DxForm ref={formRef} url={API_URL} fields={fields} events={supplierEvents} />
      </DxDialog>
    </div>
  );
};
