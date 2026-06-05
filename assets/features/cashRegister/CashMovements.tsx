import './CashMovements.scss';
import { DataGridView, DialogView, FormView } from '@nubit/crud';
import NumberUtils from '../../shared/lib/NumberUtils';
import { useEffect, useState } from 'react';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import cashMovementsFields from './components/fields/cashMovementsFields';
import { useNavigate, useParams } from 'react-router-dom';
import { useEvents } from '@nubit/core';
import { HTTP_ERROR_EVENT } from '@shared/api/HttpClient';

type CashMovementsParams = {
  id: string;
};

export const CashMovements = () => {
  const { id } = useParams<CashMovementsParams>();
  const API_URL = API + 'cash-movements';
  const fields = cashMovementsFields(id);
  const [gridError, setGridError] = useState<string | null>(null);
  const navigate = useNavigate();
  const [on] = useEvents();

  const EVENTS = {
    ADD: 'cash-movement:add',
    EDIT: 'cash-movement:edit',
    DELETE: 'cash-movement:delete',
    SAVE: 'cash-movement:save',
    CANCEL: 'cash-movement:cancel',
    SUCCESS: 'cash-movement:success',
    LOADING: 'cash-movement:loading',
  };

  const toolbar = {
    primary: [
      {
        text: 'Cerrar Caja',
        icon: 'ph-lock-simple',
        type: 'secondary' as const,
        onClick: () => {
          navigate(`/cash-register/${id}/close`);
        },
      },
    ],
  };

  const onContentReady = () => {
    setGridError(null);
  };

  useEffect(() => {
    on<string>(HTTP_ERROR_EVENT, (message) => {
      setGridError(message || 'No se pudo cargar movimientos de caja');
    });
  }, [on]);

  return (
    <div className="cash-movements-page">
      <DataGridView
        id="cash-movements"
        title="Movimientos de caja"
        toolbar={toolbar}
        url={API + 'cash-shifts/' + id + '/movements'}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={EVENTS}
        summaryFields={[
          {
            column: 'amount',
            label: 'Balance',
            summaryType: ({ rows }) => rows.reduce(
              (total, item) => total + (item.type === 'income' ? Number(item.amount) : -Number(item.amount)),
              0,
            ),
            valueFormat: (value) => NumberUtils.formatCurrency(Number(value ?? 0)),
          },
        ]}
        onContentReady={onContentReady}
        paging={false}
      />
      <DialogView title="Movimiento de caja" events={EVENTS}>
        <FormView url={API_URL} fields={fields} events={EVENTS} />
      </DialogView>
    </div>
  );
};
