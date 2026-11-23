import './CashRegisterHub.scss';
import { Navigate, NavLink, Outlet, useLocation } from 'react-router-dom';
import { Alert } from '@nubitio/ui';
import { hasPermission } from '@shared/permissions';
import { CashboxKpis } from './components/CashboxKpis';
import { useCashboxOverview } from './hooks/useCashboxOverview';

const TODAY_FORMAT = new Intl.DateTimeFormat('es-PE', {
  weekday: 'long',
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
});

/** The tabs where the figures and the warning of the day help; the others need the room for their table. */
const OVERVIEW_PATHS = ['/cashbox/dashboard', '/cashbox/shifts'];

interface Tab {
  key: string;
  label: string;
  path: string;
  visible: boolean;
}

const capitalize = (text: string) => text.charAt(0).toUpperCase() + text.slice(1);

export const CashRegisterHubLayout = () => {
  const { pathname } = useLocation();
  const { summary, totals } = useCashboxOverview();
  // `/cashbox` alone has nothing to show: it lands on the registers.
  const atRoot = pathname.replace(/\/+$/, '') === '/cashbox';
  const showOverview = OVERVIEW_PATHS.some((path) => pathname.startsWith(path));

  if (atRoot) return <Navigate to="/cashbox/dashboard" replace />;

  const tabs: Tab[] = [
    { key: 'dashboard', label: 'Cajas', path: '/cashbox/dashboard', visible: true },
    { key: 'shifts', label: 'Historial de turnos', path: '/cashbox/shifts', visible: true },
    {
      key: 'control',
      label: 'Control',
      path: '/cashbox/control',
      visible: hasPermission('activity.view'),
    },
    {
      key: 'settings',
      label: 'Configuración de cajas',
      path: '/cashbox/registers',
      visible: true,
    },
  ];

  return (
    <div className="cashbox-hub" data-testid="cashbox-hub">
      <header className="cashbox-hub__header">
        <nav className="cashbox-hub__crumbs" aria-label="Ruta">
          Finanzas / <span>Cajas y turnos</span>
        </nav>
        <h1 className="cashbox-hub__title">Cajas y turnos</h1>
        <p className="cashbox-hub__date">{capitalize(TODAY_FORMAT.format(new Date()))}</p>
      </header>

      {showOverview && (
        <>
          <CashboxKpis totals={totals} summary={summary.data} />
          {totals.stale.length > 0 && (
            <Alert tone="warning" data-testid="cashbox-stale-alert">
              <strong>
                {totals.stale.length === 1
                  ? `${totals.stale[0].name} lleva más de 24 horas con el turno abierto.`
                  : `${totals.stale.length} cajas llevan más de 24 horas con el turno abierto.`}
              </strong>{' '}
              {totals.stale.length === 1
                ? 'Haz el arqueo para poder abrir uno nuevo.'
                : `Haz el arqueo de ${totals.stale.map((register) => register.name).join(', ')}.`}
            </Alert>
          )}
        </>
      )}

      <nav className="cashbox-tabs" aria-label="Secciones de caja">
        {tabs
          .filter((tab) => tab.visible)
          .map((tab) => {
            const active = pathname.startsWith(tab.path);
            return (
              <NavLink
                key={tab.key}
                to={tab.path}
                className={`cashbox-tabs__tab${active ? ' is-active' : ''}`}
                aria-current={active ? 'page' : undefined}
              >
                {tab.label}
              </NavLink>
            );
          })}
      </nav>

      <div className="cashbox-hub__content">
        <Outlet />
      </div>
    </div>
  );
};
