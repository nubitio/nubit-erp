import './CashMovements.scss';
import { DataGridView, DialogView, FormView, type GridHandle } from '@nubit/core';
import NumberUtils from '../../shared/lib/NumberUtils';
import { useEffect, useRef, useState } from 'react';
import { API_BASE_URL } from '@app/constants';
import cashMovementsFields from './components/fields/cashMovementsFields';
import { useNavigate, useParams } from 'react-router-dom';
import { useEvents } from '@nubit/core';
import { HTTP_ERROR_EVENT } from '@shared/api/HttpClient';

type CashMovementsParams = {
  id: string;
};

export const CashMovements = () => {
  const { id } = useParams<CashMovementsParams>();
  const API_URL = API_BASE_URL + 'cash-movements';
  const gridRef = useRef<GridHandle>(null);
  const fields = cashMovementsFields(id);
  const [balance, setBalance] = useState<number | undefined>(undefined);
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

  const toolbar = [
    {
      text: 'Cerrar Caja',
      type: 'info',
      onClick: () => {
        navigate(`/cash-register/${id}/close`);
      },
    },
  ];

  const onContentReady = () => {
    setGridError(null);
    let balance = 0;
    gridRef.current?.loadData().then((response: any) => {
      const rows = Array.isArray(response) ? response : response?.data ?? [];
      rows.forEach((item: any) => {
          balance += item.type === 'income' ? Number(item.amount) : -Number(item.amount);
        });
      setBalance(balance);
    });
  };

  useEffect(() => {
    on<string>(HTTP_ERROR_EVENT, (message) => {
      setGridError(message || 'No se pudo cargar movimientos de caja');
    });
  }, [on]);

  return (
    <div className="cash-movements-page">
      <DataGridView
        ref={gridRef}
        id="cash-movements"
        title="Movimientos de caja"
        toolbar={toolbar}
        url={API_BASE_URL + 'cash-shifts/' + id + '/movements'}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
        fields={fields}
        events={EVENTS}
        summaryFields={[
          {
            column: 'amount',
            summaryType: 'custom',
            customizeText: (_e) => {
              return 'Balance: ' + NumberUtils.formatCurrency(balance ?? 0);
            },
          },
        ]}
        onContentReady={onContentReady}
        paging={false}
      />
      <DialogView title="Movimiento de caja" events={EVENTS}>
        <FormView url={API_URL} fields={fields} events={EVENTS} useIRI={true} />
      </DialogView>
    </div>
  );
};
