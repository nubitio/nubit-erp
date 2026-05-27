import { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { SmartCrudPage, defineResource, type ResourceToolbarContext } from '@nubit/crud';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import { CashRegisterFields } from './CashRegisterFields';

const baseResource = defineResource(`${API}cash-registers`, {
  id: 'cash-register',
  title: 'Cajas',
  fields: CashRegisterFields(),
  permissions: { canAdd: true, canEdit: true, canDelete: true },
});

export const CashRegisterPage = () => {
  const navigate = useNavigate();

  const resource = useMemo(
    () => ({
      ...baseResource,
      toolbar: ({ selectedRow }: ResourceToolbarContext) => [
        {
          text: 'Ver turnos',
          type: 'info',
          onClick: () => {
            if (!selectedRow) return;
            navigate(`/cash-register/${selectedRow.id}/shifts`);
          },
        },
      ],
    }),
    [navigate],
  );

  return <SmartCrudPage resource={resource} />;
};
