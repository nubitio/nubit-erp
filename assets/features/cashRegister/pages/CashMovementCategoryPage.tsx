import './CashMovementCategoryPage.scss';
import { useMemo } from 'react';
import { CashMovementCategoryFields } from './CashMovementCategoryFields';
import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { Constants } from '@core/common/Constants';
import { cashMovementCategoryEvents } from '@features/cashRegister';

export const CashMovementCategoryPage = () => {
  const container = 'wrapper';
  const API_URL = Constants.API_BASE_URL + 'cash-movement-categories';
  const fields = useMemo(() => CashMovementCategoryFields(), []);
  return (
    <div id={container}>
      <DxDataGrid
        id={'cash-movement-categories'}
        title={'Categorías de caja'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={cashMovementCategoryEvents}
      />
      <DxDialog events={cashMovementCategoryEvents}>
        <DxForm url={API_URL} fields={fields} events={cashMovementCategoryEvents} />
      </DxDialog>
    </div>
  );
};
