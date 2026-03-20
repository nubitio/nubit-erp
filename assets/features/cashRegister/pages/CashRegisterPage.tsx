import { useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { DxDataGrid, type DxDataGridEvents } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { Constants } from '@core/common/Constants';
import { cashRegisterEvents, createCashRegisterToolbar } from '@features/cashRegister';
import { CashRegisterFields } from './CashRegisterFields';

const API_URL = Constants.API_BASE_URL + 'cash-registers';
const fields = CashRegisterFields();

export const CashRegisterPage = () => {
  const navigate = useNavigate();
  const gridRef = useRef<DxDataGridEvents>(null);

  const toolbar = createCashRegisterToolbar({
    onViewShifts: () => {
      const row = gridRef.current?.getSelectedRow();
      if (!row) return;
      navigate(`/cash-register/${row.id}/shifts`);
    },
  });

  return (
    <div id="wrapper">
      <DxDataGrid
        ref={gridRef}
        id="cash-register"
        title="Cajas"
        url={API_URL}
        allowAdd
        allowEdit
        allowDelete
        selectionMode="single"
        fields={fields}
        events={cashRegisterEvents}
        toolbar={toolbar}
      />
      <DxDialog events={cashRegisterEvents}>
        <DxForm url={API_URL} fields={fields} events={cashRegisterEvents} />
      </DxDialog>
    </div>
  );
};
