import './CashboxPage.scss';
import { DxDataGrid, DxDataGridEvents } from '../../core/datagrid/DxDataGrid';
import { DxDialog } from '../../core/dialog/DxDialog';
import { DxForm } from '../../core/form/DxForm';
import { Constants } from '../../core/common/Constants';
import { CashboxDetailFields } from './CashboxFields';
import { useEffect, useRef, useState } from 'react';
import { httpClient } from '../../core/http/HttpClient';
import { confirm } from 'devextreme/ui/dialog';
import notify from 'devextreme/ui/notify';
import { Field } from '../../core/field/Field';
import NumberUtils from '../../core/utils/NumberUtils';
import { useNavigate } from 'react-router-dom';
import { useEvents } from '../../core/event/EventHook';
import { userInformation } from '../../core/common/UserInformation';
import { prompt } from '../../components/library/prompt-dialog/PromptDialog';

export const CashboxPage = () => {
  const container = 'wrapper';
  const gridRef = useRef<DxDataGridEvents>(null);
  const [url, setUrl] = useState<string | undefined>(undefined);
  const [cashboxId, setCashboxId] = useState<string | undefined>(undefined);
  const [cashboxBalance, setCashboxBalance] = useState<number | undefined>(undefined);
  const fieldsRef = useRef<Field[]>();
  const navigate = useNavigate();
  const [on, emit] = useEvents();

  const API_URL = Constants.API_BASE_URL + 'cashbox-details';

  const EVENTS = {
    ADD: 'cashbox:add',
    EDIT: 'cashbox:edit',
    DELETE: 'cashbox:delete',
    SAVE: 'cashbox:save',
    CANCEL: 'cashbox:cancel',
    SUCCESS: 'cashbox:success',
    LOADING: 'cashbox:loading',
  };

  const toolbar = [
    {
      text: 'Cerrar Caja',
      icon: 'close',
      type: 'warning',
      onClick: () => {
        confirm('¿Está seguro que desea cerrar la caja?', 'Cerrar Caja').then((result) => {
          if (result) {
            httpClient
              .put<any>(Constants.API_BASE_URL + `cashboxes/${cashboxId}/close`, {
                caja_id: cashboxId,
              })
              .subscribe((response) => {
                notify(
                  {
                    message: 'Caja cerrada correctamente',
                    type: 'success',
                    displayTime: 3000,
                  },
                  { position: 'top right' },
                );
                setCashboxId(undefined);
                setCashboxBalance(undefined);
                setUrl(undefined);
              });
          }
        });
      },
    },
  ];

  const checkCashbox = () => {
    httpClient
      .get<any>(Constants.API_BASE_URL + `cashboxes/${userInformation().currentBranchId}/last`)
      .subscribe({
        next: (response) => {
          if (response.data.estado === 1) {
            setupCashbox(response.data);
            return;
          }
          openCashbox(response.data.balance);
        },
        error: (error) => {
          openCashbox(0);
        },
      });
  };

  const openCashbox = (balance: Number) => {
    prompt('Caja', 'Ingrese el monto inicial de la caja', 'number', balance).then((amount) => {
      if (amount !== null && amount !== '' && !isNaN(Number(amount)) && Number(amount) > 0) {
        const payload = {
          branch_id: userInformation().currentBranchId,
          user_id: userInformation().currentUserId,
          amount: Number(amount),
        };
        httpClient
          .post<any>(Constants.API_BASE_URL + 'cashboxes/open', payload)
          .subscribe((response) => {
            notify(
              {
                message: 'Caja abierta correctamente',
                type: 'success',
                displayTime: 3000,
              },
              { position: 'top right' },
            );
            setupCashbox(response.data);
          });
      } else {
        navigate('/');
      }
    });
  };

  const setupCashbox = (cashbox: any) => {
    setUrl(Constants.API_BASE_URL + 'cashboxes/' + cashbox.caja_id + '/details');
    fieldsRef.current = CashboxDetailFields(cashbox.caja_id);
    setCashboxId(cashbox.caja_id);
    setCashboxBalance(cashbox.balance);
  };

  const setupObservers = () => {
    on(EVENTS.SUCCESS, (payload) => {
      let balance = 0;
      gridRef.current
        ?.getDataSource()
        ?.load()
        .then((response: any) => {
          response.data.forEach((item: any) => {
            balance += item.tipo_id === 1 ? item.monto : -item.monto;
          });
          setCashboxBalance(balance);
        });
    });
  };

  useEffect(() => {
    checkCashbox();
    setupObservers();
  }, [cashboxId]);

  const render = (fields) => {
    return (
      <div>
        <DxDataGrid
          ref={gridRef}
          id="cashbox"
          title="Cashbox"
          toolbar={toolbar}
          url={url!}
          allowAdd={true}
          allowEdit={true}
          allowDelete={true}
          fields={fields}
          events={EVENTS}
          summaryFields={[
            {
              column: 'monto',
              summaryType: 'custom',
              customizeText: (e) => {
                return 'Balance: ' + NumberUtils.formatCurrency(cashboxBalance ?? 0);
              },
            },
          ]}
          paging={false}
        />
        <DxDialog events={EVENTS}>
          <DxForm url={API_URL} fields={fields} events={EVENTS} />
        </DxDialog>
      </div>
    );
  };

  return <div id={container}>{cashboxId ? render(fieldsRef.current) : null}</div>;
};
