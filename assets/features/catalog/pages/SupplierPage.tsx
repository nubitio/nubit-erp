import './SupplierPage.scss';
import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm, DxFormEvents } from '@core/form/DxForm';
import { supplierEvents } from '@features/supplier';
import { SupplierFields } from './SupplierFields';
import { useMemo, useRef } from 'react';
import { supplierEndpoints } from '../api/catalogEndpoints';

export const SupplierPage = () => {
  const container = 'wrapper';
  const formRef = useRef<DxFormEvents | null>(null);
  const fields = useMemo(() => SupplierFields(formRef), [formRef]);

  const API_URL = supplierEndpoints.list();

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
