import { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { useAuth } from '@contexts/auth';
import { cashboxService } from '@features/cashRegister';
import { lastValueFrom } from 'rxjs';

export interface QuerySuggestion {
  id: string;
  label: string;
  query: string;
}

type CashSessionStatus = 'open' | 'closed' | 'unknown';

const ADMIN_ROLES = ['SUPER_ADMIN', 'ADMIN'];
const MAX_CHIPS = 3;

function isAdminRole(roles: string[]): boolean {
  return roles.some((r) => ADMIN_ROLES.includes(r));
}

function getRouteSuggestions(
  pathname: string,
  isAdmin: boolean,
  cashStatus: CashSessionStatus,
): QuerySuggestion[] {
  const suggestions: QuerySuggestion[] = [];

  // Cash-session-aware prepend
  if (cashStatus === 'closed') {
    suggestions.push({
      id: 'cash-open',
      label: '¿Cómo abrir la caja?',
      query: '¿Cómo puedo abrir la caja registradora?',
    });
  }

  // Route-specific suggestions
  if (pathname.startsWith('/dashboard')) {
    if (isAdmin) {
      suggestions.push(
        {
          id: 'dash-sales',
          label: 'Resumen de ventas de hoy',
          query: '¿Cuál es el resumen de ventas de hoy?',
        },
        {
          id: 'dash-stock',
          label: '¿Hay productos por agotarse?',
          query: '¿Hay productos próximos a agotarse?',
        },
        {
          id: 'dash-sunat',
          label: '¿Errores SUNAT recientes?',
          query: '¿Ha habido errores recientes de SUNAT?',
        },
      );
    } else {
      suggestions.push(
        {
          id: 'dash-today',
          label: '¿Cuánto he vendido hoy?',
          query: '¿Cuánto he vendido hoy?',
        },
        {
          id: 'dash-cash',
          label: '¿Tengo la caja abierta?',
          query: '¿Tengo la caja abierta actualmente?',
        },
        {
          id: 'dash-summary',
          label: 'Resumen del día',
          query: 'Dame un resumen de las operaciones del día.',
        },
      );
    }
  } else if (pathname.startsWith('/pos') || pathname.startsWith('/sales')) {
    suggestions.push(
      {
        id: 'pos-sold',
        label: '¿Cuánto llevo vendido?',
        query: '¿Cuánto llevo vendido en esta sesión?',
      },
      {
        id: 'pos-customer',
        label: '¿El cliente tiene historial?',
        query: '¿El cliente tiene historial de compras en el sistema?',
      },
      {
        id: 'pos-top',
        label: 'Productos más vendidos',
        query: '¿Cuáles son los productos más vendidos?',
      },
    );
  } else if (pathname.startsWith('/inventory') || pathname.startsWith('/kardex')) {
    suggestions.push(
      {
        id: 'inv-low',
        label: '¿Productos por agotarse?',
        query: '¿Qué productos están por agotarse?',
      },
      {
        id: 'inv-restock',
        label: '¿Cuándo reponer stock?',
        query: '¿Cuándo debo reponer stock de los productos con bajo inventario?',
      },
      {
        id: 'inv-rotation',
        label: 'Rotación de inventario',
        query: '¿Cuál es mi rotación de inventario?',
      },
    );
  } else {
    // Default fallback
    suggestions.push(
      {
        id: 'default-help',
        label: '¿En qué puedo ayudarte?',
        query: '¿En qué me puedes ayudar?',
      },
      {
        id: 'default-summary',
        label: 'Resumen del día',
        query: 'Dame un resumen de las operaciones del día.',
      },
      {
        id: 'default-sunat',
        label: 'Estado SUNAT',
        query: '¿Cuál es el estado actual de mis comprobantes en SUNAT?',
      },
    );
  }

  return suggestions.slice(0, MAX_CHIPS);
}

export function useQuerySuggestions(): { suggestions: QuerySuggestion[] } {
  const { pathname } = useLocation();
  const { user } = useAuth();
  const [cashStatus, setCashStatus] = useState<CashSessionStatus>('unknown');

  const roles: string[] = user?.roles ?? [];
  const isAdmin = isAdminRole(roles);

  useEffect(() => {
    let cancelled = false;

    lastValueFrom(cashboxService.getDashboard())
      .then((response) => {
        if (cancelled) return;
        const registers = response.data ?? [];
        if (registers.some((r) => r.status === 'open')) {
          setCashStatus('open');
        } else if (registers.length > 0) {
          setCashStatus('closed');
        } else {
          setCashStatus('unknown');
        }
      })
      .catch(() => {
        if (!cancelled) setCashStatus('unknown');
      });

    return () => {
      cancelled = true;
    };
  }, [pathname]);

  const suggestions = getRouteSuggestions(pathname, isAdmin, cashStatus);

  return { suggestions };
}
