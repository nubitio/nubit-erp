import './UnitOfMeasure.scss';
import { DxDataGrid } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm } from '@platform/form/DxForm';
import { unitOfMeasureEvents } from '@features/unitOfMeasure';
import { UnitOfMeasureFields } from './UnitOfMeasureFields';
import { unitOfMeasureEndpoints } from '../api/catalogEndpoints';

export const UnitOfMeasurePage = () => {
  const container = 'wrapper';
  const fields = UnitOfMeasureFields();

  const API_URL = unitOfMeasureEndpoints.list();

  return (
    <div id={container}>
      <DxDataGrid
        id={'unitOfMeasure'}
        title={'Unidades de medida'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={unitOfMeasureEvents}
      />
      <DxDialog events={unitOfMeasureEvents}>
        <DxForm url={API_URL} fields={fields} events={unitOfMeasureEvents} />
      </DxDialog>
    </div>
  );
};
