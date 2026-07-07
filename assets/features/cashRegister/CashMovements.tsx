import './CashMovements.scss';
import { DataGridView, DialogView, FormView } from '@nubitio/crud';
import NumberUtils from '../../shared/lib/NumberUtils';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import cashMovementsFields from './components/fields/cashMovementsFields';
import { useNavigate, useParams } from 'react-router-dom';

type CashMovementsParams = {
  id: string;
};

export const CashMovements = () => {
  const { id } = useParams<CashMovementsParams>();
  const API_URL = API + 'cash-movements';
  const fields = cashMovementsFields(id);
  const navigate = useNavigate();

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
          navigate(`/cash-session/${id}/close`);
        },
      },
    ],
  };

  return (
    <div className="cash-movements-page">
      <DataGridView
        id="cash-movements"
        title="Movimientos de caja"
        toolbar={toolbar}
        url={API + 'cash-sessions/' + id + '/movements'}
        allowAdd={true}
        allowEdit={false}
        allowDelete={false}
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
        paging={false}
      />
      <DialogView title="Movimiento de caja" events={EVENTS}>
        <FormView url={API_URL} fields={fields} events={EVENTS} />
      </DialogView>
    </div>
  );
};
