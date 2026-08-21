import './CashRegisterDashboard.scss';
import cashRegisterDashboardFields from './components/fields/cashRegisterDashboardFields';
import { DataGridView, type GridHandle } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { useNavigate } from 'react-router-dom';
import { useRef } from 'react';

export const CashRegisterDashboard = () => {
  const url = API + 'cash-registers/dashboard';
  const navigate = useNavigate();
  const gridRef = useRef<GridHandle | null>(null);
  const fields = cashRegisterDashboardFields(navigate);

  const EVENTS = {};

  const TOOLBAR = {
    utility: [
      {
        text: 'Nueva caja',
        icon: 'ph-plus-circle',
        hint: 'Crear una caja para esta sucursal',
        onClick: () => navigate('/cashbox/registers'),
      },
    ],
  };

  return (
    <div className="cash-register-dashboard-page">
      <DataGridView
        ref={gridRef}
        id="cashbox"
        title="Cajas"
        url={url}
        fields={fields}
        events={EVENTS}
        toolbar={TOOLBAR}
        paging={false}
        filterRow={false}
        emptyState={{
          title: 'Sin cajas',
          description: 'Crea una caja para abrir turno, cobrar en efectivo y cerrar al final del día.',
          icon: 'cash-register',
        }}
      />
    </div>
  );
};
