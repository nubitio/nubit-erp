import { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { SchemaCrudPage, defineResource, type ResourceToolbarContext } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { CashRegisterFields } from './CashRegisterFields';

const CASH_REGISTER_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['name', 'branch', 'isActive'],
  },
];

const baseResource = defineResource(`${API}cash-registers`, {
  id: 'cash-register',
  title: 'Cajas',
  fields: CashRegisterFields(),
  viewMode: 'dialog',
  defaultPreset: 'basic',
  columnPresets: CASH_REGISTER_COLUMN_PRESETS,
  emptyState: {
    title: 'Sin cajas registradas',
    description: 'Crea una caja para operar el punto de venta.',
    icon: 'cash-register',
  },
  permissions: { canEdit: true, canDelete: true },
});

export const CashRegisterPage = () => {
  const navigate = useNavigate();

  const resource = useMemo(
    () => ({
      ...baseResource,
      toolbar: ({ selectedRow }: ResourceToolbarContext) => ({
        selection: [
          {
            text: 'Ver turnos',
            type: 'info',
            group: 'navigation',
            groupLabel: 'Navegación',
            onClick: () => {
              if (!selectedRow) return;
              navigate(`/cashbox/shifts?caja=${selectedRow.id}`);
            },
          },
        ],
      }),
    }),
    [navigate],
  );

  return <SchemaCrudPage resource={resource} />;
};
