import cashRegisterDashboardFields from '../components/fields/cashRegisterDashboardFields';
import { DxDataGrid } from '../../../core/datagrid/DxDataGrid';
import { Constants } from '../../../core/common/Constants';
import { useNavigate } from 'react-router-dom';

export const CashRegisterDashboard = () => {
  const url = Constants.API_BASE_URL + 'cash-registers/dashboard';
  const navigate = useNavigate();
  const fields = cashRegisterDashboardFields(navigate);

  const EVENTS = {};

  return (
    <>
      <DxDataGrid
        id="cashbox"
        title="Cashbox"
        url={url}
        fields={fields}
        events={EVENTS}
        paging={false}
      />
    </>
  );
};
