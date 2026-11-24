import './CashRegisterDashboard.scss';
import { useNavigate } from 'react-router-dom';
import { Alert, EmptyState } from '@nubitio/ui';
import { RegisterCard } from './components/RegisterCard';
import { RecentShiftsTable } from './components/RecentShiftsTable';
import { useCashboxRegisters } from './hooks/useCashboxOverview';
import { summarizeRegisters } from './model/registersSummary';

/** "Cajas" tab: a card per register and the latest shifts under them. */
export const CashRegisterDashboard = () => {
  const navigate = useNavigate();
  const { data, isLoading, isError } = useCashboxRegisters();
  const registers = data ?? [];
  const staleIds = new Set(summarizeRegisters(registers).stale.map((register) => register.id));

  return (
    <div className="cashbox-dashboard" data-testid="cashbox-dashboard">
      {isError && <Alert tone="danger">No se pudieron cargar las cajas. Intenta actualizar.</Alert>}

      {!isLoading && !isError && registers.length === 0 ? (
        <EmptyState
          title="Sin cajas"
          description="Crea una caja en Configuración de cajas para abrir turno, cobrar en efectivo y cerrar al final del día."
          icon="cash-register"
        />
      ) : (
        <div className="cashbox-grid">
          {registers.map((register) => (
            <RegisterCard
              key={register.id}
              register={register}
              stale={staleIds.has(register.id)}
              onNavigate={navigate}
            />
          ))}
        </div>
      )}

      <RecentShiftsTable limit={5} />
    </div>
  );
};
