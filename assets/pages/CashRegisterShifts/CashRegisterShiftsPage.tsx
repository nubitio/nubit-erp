import './CashRegisterShiftsPage.scss';
import {useNavigate, useParams} from 'react-router-dom';
import {DxDataGrid, DxDataGridEvents} from '../../core/datagrid/DxDataGrid';
import {CashRegisterShiftsFields} from './CashRegisterShiftsFields';
import {Constants} from '../../core/common/Constants';
import {useRef} from 'react';

type CashRegisterShiftParams = {
  id: string;
};

export const CashRegisterShiftsPage = () => {
  const {id} = useParams<CashRegisterShiftParams>();
  const navigate = useNavigate();
  const gridRef = useRef<DxDataGridEvents>(null);
  const fields = CashRegisterShiftsFields();
  const API_URL = `${Constants.API_BASE_URL}cash-registers/${id}/shifts`;

  const toolbar = [
    {
      text: 'Exportar PDF',
      icon: 'print',
      type: 'default',
      onClick: () => {
        const row = gridRef.current?.getSelectedRow();
        if (!row) return;
        window.open(`${Constants.API_BASE_URL}cash-shifts/${row.id}/closure-report`, '_blank');
      },
    },
  ];

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
