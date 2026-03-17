import './CashRegisterShiftsPage.scss';
import { useParams } from 'react-router-dom';
import { DxDataGrid, DxDataGridEvents } from '@core/datagrid/DxDataGrid';
import { CashRegisterShiftsFields } from './CashRegisterShiftsFields';
import { Constants } from '@core/common/Constants';
import { createCashRegisterShiftsToolbar } from '@features/cashRegister';
import { useMemo, useRef } from 'react';

type CashRegisterShiftParams = {
  id: string;
};

export const CashRegisterShiftsPage = () => {
  const { id } = useParams<CashRegisterShiftParams>();
  const gridRef = useRef<DxDataGridEvents>(null);
  const fields = useMemo(() => CashRegisterShiftsFields(), []);
  const API_URL = `${Constants.API_BASE_URL}cash-registers/${id}/shifts`;

  const toolbar = createCashRegisterShiftsToolbar({
    onExportPdf: () => {
      const row = gridRef.current?.getSelectedRow();
      if (!row) return;
      window.open(`${Constants.API_BASE_URL}cash-shifts/${row.id}/closure-report`, '_blank');
    },
  });

  return (
    <div id="wrapper">
      <DxDataGrid
        ref={gridRef}
        id="cash-register-shifts"
        title="Historial de turnos"
        url={API_URL}
        allowAdd={false}
        allowEdit={false}
        allowDelete={false}
        selectionMode="single"
        fields={fields}
        events={{}}
        toolbar={toolbar}
        hasBackButton={true}
      />
    </div>
  );
};
