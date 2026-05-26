import { useMemo, useRef } from 'react';
import { useParams } from 'react-router-dom';
import { DataGridView, type GridHandle } from '@nubit/crud';
import { API_BASE_URL } from '@app/constants';
import { createCashRegisterShiftsToolbar } from '@features/cashRegister';
import { CashRegisterShiftsFields } from './CashRegisterShiftsFields';

type CashRegisterShiftParams = {
  id: string;
};

export const CashRegisterShiftsPage = () => {
  const { id } = useParams<CashRegisterShiftParams>();
  const gridRef = useRef<GridHandle>(null);
  const fields = useMemo(() => CashRegisterShiftsFields(), []);
  const API_URL = `${API_BASE_URL}cash-registers/${id}/shifts`;

  const toolbar = createCashRegisterShiftsToolbar({
    onExportPdf: () => {
      const row = gridRef.current?.getSelectedRow();
      if (!row) return;
      window.open(`${API_BASE_URL}cash-shifts/${row.id}/closure-report`, '_blank');
    },
  });

  return (
    <div id="wrapper">
      <DataGridView
        ref={gridRef}
        id="cash-register-shifts"
        title="Historial de turnos"
        url={API_URL}
        selectionMode="single"
        fields={fields}
        events={{}}
        toolbar={toolbar}
        hasBackButton
      />
    </div>
  );
};
