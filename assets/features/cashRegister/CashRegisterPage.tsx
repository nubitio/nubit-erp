import { useMemo, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { SmartCrudPage, defineResource, type DataRecord } from '@nubit/crud';
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
  const selectedRowRef = useRef<DataRecord | null>(null);

  const resource = useMemo(
    () => ({
      ...baseResource,
      toolbar: [
        {
          text: 'Ver turnos',
          type: 'info',
          onClick: () => {
            if (!selectedRowRef.current) return;
            navigate(`/cash-register/${selectedRowRef.current.id}/shifts`);
          },
        },
      ],
    }),
    [navigate],
  );

  return (
    <SmartCrudPage
      resource={resource}
      onSelectionChanged={(rows) => {
        selectedRowRef.current = rows[0] ?? null;
      }}
    />
  );
};
