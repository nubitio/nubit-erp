import './CashRegisterDashboard.scss';
import cashRegisterDashboardFields from './components/fields/cashRegisterDashboardFields';
import { DataGridView, type GridHandle } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { useNavigate } from 'react-router-dom';
import { useEffect, useRef, useState } from 'react';
import { useEvents } from '@nubitio/core';
import { HTTP_ERROR_EVENT } from '@shared/api/HttpClient';

export const CashRegisterDashboard = () => {
  const url = API + 'cash-registers/dashboard';
  const navigate = useNavigate();
  const gridRef = useRef<GridHandle | null>(null);
  const [gridError, setGridError] = useState<string | null>(null);
  const [on] = useEvents();
  const fields = cashRegisterDashboardFields(navigate);

  const EVENTS = {};

  useEffect(() => {
    on<string>(HTTP_ERROR_EVENT, (message) => {
      setGridError(message || 'No se pudo cargar el dashboard de cajas');
    });
  }, [on]);

  return (
    <div className="cash-register-dashboard-page">
      <DataGridView
        ref={gridRef}
        id="cashbox"
        title="Cajas"
        url={url}
        fields={fields}
        events={EVENTS}
        paging={false}
        onContentReady={() => setGridError(null)}
      />
    </div>
  );
};
