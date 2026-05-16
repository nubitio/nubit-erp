import './CashMovements.scss';
import { DxDataGrid, DxDataGridEvents } from '@core/datagrid/DxDataGrid';
import NumberUtils from '../../shared/lib/NumberUtils';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { useEffect, useRef, useState } from 'react';
import { Constants } from '@core/common/Constants';
import cashMovementsFields from './components/fields/cashMovementsFields';
import { useNavigate, useParams } from 'react-router-dom';
import { useEvents } from '@core/event/EventHook';
import { HTTP_ERROR_EVENT } from '@shared/api/HttpClient';

type CashMovementsParams = {
  id: string;
};

export const CashMovements = () => {
  const { id } = useParams<CashMovementsParams>();
  const API_URL = Constants.API_BASE_URL + 'cash-movements';
  const gridRef = useRef<DxDataGridEvents>(null);
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
    gridRef.current
      ?.getDataSource()
      ?.load()
      .then((response: any) => {
        response.data.forEach((item: any) => {
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
      <DxDataGrid
        ref={gridRef}
        id="cash-movements"
        title="Movimientos de caja"
        toolbar={toolbar}
        url={Constants.API_BASE_URL + 'cash-shifts/' + id + '/movements'}
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
      <DxDialog title="Movimiento de caja" events={EVENTS}>
        <DxForm url={API_URL} fields={fields} events={EVENTS} useIRI={true} />
      </DxDialog>
    </div>
  );
};
