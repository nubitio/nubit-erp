import cashRegisterDashboardFields from '../components/fields/cashRegisterDashboardFields';
import { DxDataGrid } from '../../../core/datagrid/DxDataGrid';
import { Constants } from '../../../core/common/Constants';

export const CashRegisterDashboard = () => {
  const url = Constants.API_BASE_URL + 'cash-registers/dashboard';
  const fields = cashRegisterDashboardFields();

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
