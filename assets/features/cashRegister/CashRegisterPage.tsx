import { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { SmartCrudPage, defineResource, type ResourceToolbarContext } from '@nubit/crud';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import { CashRegisterFields } from './CashRegisterFields';

const CASH_REGISTER_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['name', 'branch'],
  },
];

const baseResource = defineResource(`${API}cash-registers`, {
  id: 'cash-register',
  title: 'Cajas',
  fields: CashRegisterFields(),
  viewMode: { mode: 'drawer', drawerWidth: 560 },
  defaultPreset: 'basic',
  columnPresets: CASH_REGISTER_COLUMN_PRESETS,
  formLayout: {
    type: 'tabs',
    tabs: [
      {
        label: 'General',
        icon: 'ph-cash-register',
        fields: ['name', 'branch'],
      },
    ],
  },
  emptyState: {
    title: 'Sin cajas registradas',
    description: 'Crea una caja para operar el punto de venta.',
    icon: 'cash-register',
  },
  permissions: { canAdd: true, canEdit: true, canDelete: true },
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
              navigate(`/cash-register/${selectedRow.id}/shifts`);
            },
          },
        ],
      }),
    }),
    [navigate],
  );

  return <SmartCrudPage resource={resource} />;
};
