import './CashRegisterDashboard.scss';
import cashRegisterDashboardFields from '../components/fields/cashRegisterDashboardFields';
import { DxDataGrid, DxDataGridEvents } from '../../../core/datagrid/DxDataGrid';
import { Constants } from '../../../core/common/Constants';
import { useNavigate } from 'react-router-dom';
import { useEffect, useRef, useState } from 'react';
import { useEvents } from '../../../core/event/EventHook';
import { HTTP_ERROR_EVENT } from '../../../shared/api/HttpClient';
import { AsyncState } from '../../../shared/ui/async-state/AsyncState';
import { Item } from 'devextreme-react/data-grid';

export const CashRegisterDashboard = () => {
  const url = Constants.API_BASE_URL + 'cash-registers/dashboard';
  const navigate = useNavigate();
  const gridRef = useRef<DxDataGridEvents | null>(null);
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
      <DxDataGrid
        ref={gridRef}
        id="cashbox"
        title="Cajas"
        url={url}
        fields={fields}
        events={EVENTS}
        paging={false}
        onContentReady={() => setGridError(null)}
        beforeToolbar={
          gridError
            ? () => (
                <Item location="before" locateInMenu="never">
                  <div className="cash-register-dashboard-toolbar-state">
                    <AsyncState
                      compact
                      variant="error"
                      title="Error de carga"
                      description={gridError}
                      onRetry={() => gridRef.current?.refresh()}
                    />
                  </div>
                </Item>
              )
            : undefined
        }
      />
    </div>
  );
};
