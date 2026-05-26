import { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { SmartCrudPage, defineResource } from '@nubit/crud';
import { API_BASE_URL } from '@app/constants';
import { cashRegisterEvents } from '@features/cashRegister';
import { CashRegisterFields } from './CashRegisterFields';

const baseResource = defineResource({
  id: 'cash-register',
  title: 'Cajas',
  apiUrl: `${API_BASE_URL}cash-registers`,
  fields: CashRegisterFields(),
  events: cashRegisterEvents,
  permissions: { canAdd: true, canEdit: true, canDelete: true },
});

export const CashRegisterPage = () => {
  const navigate = useNavigate();

  const resource = useMemo(
    () => ({
      ...baseResource,
      toolbar: ({ selectedRow }) => [
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
