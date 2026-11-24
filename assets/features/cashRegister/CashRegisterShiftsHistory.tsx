import './CashRegisterDashboard.scss';
import { useSearchParams } from 'react-router-dom';
import { Combobox } from '@nubitio/ui';
import { RecentShiftsTable } from './components/RecentShiftsTable';
import { useCashboxRegisters } from './hooks/useCashboxOverview';

/**
 * "Historial de turnos" tab: the shifts of the whole branch, or of one register (`?caja=`), paged, with the
 * closing report of each closed one.
 */
export const CashRegisterShiftsHistory = () => {
  const [params, setParams] = useSearchParams();
  const { data: registers } = useCashboxRegisters();
  const selected = params.get('caja');
  const registerId = selected && Number(selected) > 0 ? Number(selected) : undefined;

  return (
    <div className="cashbox-dashboard">
      <div className="cashbox-filter">
        <Combobox
          aria-label="Caja"
          placeholder="Todas las cajas"
          clearable
          options={(registers ?? []).map((register) => ({
            value: String(register.id),
            label: register.name,
          }))}
          value={registerId ? String(registerId) : null}
          onChange={(value) => setParams(value ? { caja: value } : {}, { replace: true })}
        />
      </div>
      <RecentShiftsTable
        // A new register starts again from the first page.
        key={registerId ?? 'all'}
        limit={15}
        paged
        registerId={registerId}
        title="Historial de turnos"
      />
    </div>
  );
};
