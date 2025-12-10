import './CashRegisterShiftsPage.scss';
import { useParams, useNavigate } from 'react-router-dom';
import { DxDataGrid } from '../../core/datagrid/DxDataGrid';
import { CashRegisterShiftsFields } from './CashRegisterShiftsFields';
import { Constants } from '../../core/common/Constants';

type CashRegisterShiftParams = {
  id: string;
};

export const CashRegisterShiftsPage = () => {
  const { id } = useParams<CashRegisterShiftParams>();
  const navigate = useNavigate();
  const fields = CashRegisterShiftsFields();
  const API_URL = `${Constants.API_BASE_URL}cash-registers/${id}/shifts`;

  return (
    <div id="wrapper">
      <DxDataGrid
        id="cash-register-shifts"
        title="Historial de turnos"
        url={API_URL}
        allowAdd={false}
        allowEdit={false}
        allowDelete={false}
        fields={fields}
        events={{}}
        toolbar={[
          {
            text: 'Volver',
            icon: 'chevronleft',
            type: 'normal',
            onClick: () => navigate(-1),
          },
        ]}
      />
    </div>
  );
};
