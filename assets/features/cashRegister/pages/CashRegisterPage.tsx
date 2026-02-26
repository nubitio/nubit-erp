import './CashRegisterPage.scss';
import { Constants } from '@platform/common/Constants';
import { DxDataGrid, DxDataGridEvents } from '@platform/datagrid/DxDataGrid';
import { DxForm } from '@platform/form/DxForm';
import { DxDialog } from '@platform/dialog/DxDialog';
import { cashRegisterEvents, createCashRegisterToolbar } from '@features/cashRegister';
import { CashRegisterFields } from './CashRegisterFields';
import { useNavigate } from 'react-router-dom';
import { useRef } from 'react';

export const CashRegisterPage = () => {
  const navigate = useNavigate();
  const gridRef = useRef<DxDataGridEvents>(null);
  const container = 'wrapper';
  const API_URL = Constants.API_BASE_URL + 'cash-registers';
  const fields = CashRegisterFields();
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
