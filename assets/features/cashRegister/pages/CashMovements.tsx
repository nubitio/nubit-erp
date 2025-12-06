import { DxDataGrid, DxDataGridEvents } from '../../../core/datagrid/DxDataGrid';
import NumberUtils from '../../../core/utils/NumberUtils';
import { DxDialog } from '../../../core/dialog/DxDialog';
import { DxForm } from '../../../core/form/DxForm';
import { useRef, useState } from 'react';
import { Constants } from '../../../core/common/Constants';
import cashMovementsFields from '../components/fields/cashMovementsFields';
import { useNavigate, useParams } from 'react-router-dom';

type CashMovementsParams = {
  id: string;
};

export const CashMovements = () => {
  const { id } = useParams<CashMovementsParams>();
  const API_URL = Constants.API_BASE_URL + 'cash-movements';
  const gridRef = useRef<DxDataGridEvents>(null);
  const fields = cashMovementsFields(id);
  const [balance, setBalance] = useState<number | undefined>(undefined);
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
    let balance = 0;
    gridRef.current
      ?.getDataSource()
      ?.load()
      .then((response: any) => {
        response.data.forEach((item: any) => {
          balance += item.type === 'income' ? item.amount : -item.amount;
        });
        setBalance(balance);
      });
  };

  return (
    <>
      <DxDataGrid
        ref={gridRef}
        id="cash-movements"
        title="Cashbox"
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
            customizeText: (e) => {
              return 'Balance: ' + NumberUtils.formatCurrency(balance ?? 0);
            },
          },
        ]}
        onContentReady={onContentReady}
        paging={false}
      />
      <DxDialog events={EVENTS}>
        <DxForm url={API_URL} fields={fields} events={EVENTS} useIRI={true} />
      </DxDialog>
    </>
  );
};
