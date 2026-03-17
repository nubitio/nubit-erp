import './UnitOfMeasure.scss';
import { useMemo } from 'react';
import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { unitOfMeasureEvents } from '@features/unitOfMeasure';
import { UnitOfMeasureFields } from './UnitOfMeasureFields';
import { unitOfMeasureEndpoints } from '../api/catalogEndpoints';

export const UnitOfMeasurePage = () => {
  const container = 'wrapper';
  const fields = useMemo(() => UnitOfMeasureFields(), []);

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
