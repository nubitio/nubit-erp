import './CashRegisterDashboard.scss';
import cashRegisterDashboardFields from './components/fields/cashRegisterDashboardFields';
import { DataGridView, type GridHandle } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { useNavigate } from 'react-router-dom';
import { useRef } from 'react';
import { CashRegisterSummaryCards } from './CashRegisterSummaryCards';

export const CashRegisterDashboard = () => {
  const url = API + 'cash-registers/dashboard';
  const navigate = useNavigate();
  const gridRef = useRef<GridHandle | null>(null);
  const fields = cashRegisterDashboardFields(navigate);

  const EVENTS = {};

  return (
    <div className="cash-register-dashboard-page">
      <CashRegisterSummaryCards />
      <DataGridView
        ref={gridRef}
        id="cashbox"
        title="Cajas"
        url={url}
        fields={fields}
        events={EVENTS}
        paging={false}
      />
    </div>
  );
};
