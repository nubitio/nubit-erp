import './CashRegisterPage.scss';
import { Constants } from '@core/common/Constants';
import { DxDataGrid, DxDataGridEvents } from '@core/datagrid/DxDataGrid';
import { DxForm } from '@core/form/DxForm';
import { DxDialog } from '@core/dialog/DxDialog';
import { cashRegisterEvents, createCashRegisterToolbar } from '@features/cashRegister';
import { CashRegisterFields } from './CashRegisterFields';
import { useNavigate } from 'react-router-dom';
import { useMemo, useRef } from 'react';

export const CashRegisterPage = () => {
  const navigate = useNavigate();
  const gridRef = useRef<DxDataGridEvents>(null);
  const container = 'wrapper';
  const API_URL = Constants.API_BASE_URL + 'cash-registers';
  const fields = useMemo(() => CashRegisterFields(), []);
  const toolbar = createCashRegisterToolbar({
    onViewShifts: () => {
      const row = gridRef.current?.getSelectedRow();
      if (!row) return;
      navigate(`/cash-register/${row.id}/shifts`);
    },
  });

  return (
    <div id={container}>
      <DxDataGrid
        ref={gridRef}
        id={'cash-register'}
        title={'Cajas'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        selectionMode="single"
        fields={fields}
        events={cashRegisterEvents}
        toolbar={toolbar}
      />
      <DxDialog events={cashRegisterEvents}>
        <DxForm url={API_URL} fields={fields} events={cashRegisterEvents} useIRI={true} />
      </DxDialog>
    </div>
  );
};
