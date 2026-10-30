import { NavLink } from 'react-router-dom';
import { hasPermission } from '@shared/permissions';
import './FinanceNavigation.scss';

export function FinanceNavigation() {
  return (
    <nav aria-label="Secciones de finanzas" className="finance-navigation">
      {hasPermission('sales.view') && <NavLink to="/accounts-receivable">Por cobrar</NavLink>}
      {hasPermission('accounts_payable.view') && (
        <NavLink to="/accounts-payable">Por pagar</NavLink>
      )}
    </nav>
  );
}
