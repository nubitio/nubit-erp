import { useMemo, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { DataGridView, DialogView, FormView, type GridHandle } from '@nubit/core';
import { API_BASE_URL } from '@app/constants';
import { cashRegisterEvents } from '@features/cashRegister';
import { CashRegisterFields } from './CashRegisterFields';

const API_URL = API_BASE_URL + 'cash-registers';
const fields = CashRegisterFields();

export const CashRegisterPage = () => {
  const navigate = useNavigate();
  const gridRef = useRef<GridHandle>(null);

  const toolbar = useMemo(
    () => [
      {
        text: 'Ver turnos',
        type: 'info',
        onClick: () => {
          const row = gridRef.current?.getSelectedRow();
          if (!row) return;
          navigate(`/cash-register/${row.id}/shifts`);
        },
      },
    ],
    [navigate],
  );

  return (
    <div id="wrapper">
      <DataGridView
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
      <DialogView events={cashRegisterEvents}>
        <FormView url={API_URL} fields={fields} events={cashRegisterEvents} />
      </DialogView>
    </div>
  );
};
