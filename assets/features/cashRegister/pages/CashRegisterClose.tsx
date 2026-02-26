import { Column, DataGridRef, Editing, Item, Scrolling, Toolbar } from 'devextreme-react/data-grid';
import { Button, DataGrid } from 'devextreme-react';
import { useEffect, useRef, useState } from 'react';
import DataSource from 'devextreme/data/data_source';
import ArrayStore from 'devextreme/data/array_store';
import { httpClient } from '../../../shared/api/HttpClient';
import { Constants } from '../../../core/common/Constants';
import { useNavigate, useParams } from 'react-router-dom';
import toast from '../../../shared/lib/SharedUtils';
import cashRegisterService from '../services/cashRegisterService';
import './CashRegisterClose.scss';
import { confirm } from 'devextreme/ui/dialog';

type CashClosureParams = {
  id: string;
};

type CashDifference = {
  paymentMethodId: number;
  difference: number;
};

export const CashRegisterClose = () => {
  const { id } = useParams<CashClosureParams>();
  const gridRef = useRef<DataGridRef | null>(null);
  const [differenceData, setDifferenceData] = useState<CashDifference[] | undefined>(undefined);
  const navigate = useNavigate();
  const data = useRef<DataSource>(
    new DataSource({
      store: new ArrayStore({
        key: 'id',
        data: [],
      }),
    }),
  );

  const onClose = () => {
    confirm('¿Está seguro que desea cerrar la caja?', 'Cerrar Caja').then((result) => {
      if (result) {
        closeCashRegister();
      }
    });
  };

  const closeCashRegister = () => {
    const closureData = gridRef.current?.instance().getDataSource().items();
    if (id === undefined || closureData === undefined) {
      toast('Error al cerrar la caja', 'error');
      return;
    }
    const data = closureData.map((item: any) => {
      return {
        cashShift: Constants.API_BASE_URL + 'cash-shifts/' + id,
        paymentMethod: Constants.API_BASE_URL + 'payment-methods/' + item.paymentMethodId,
        expectedAmount: Number(item.expectedAmount),
        countedAmount: item.countedAmount,
        difference: differenceData?.find((diff) => diff.paymentMethodId === item.paymentMethodId)
          ?.difference,
        note: item.note,
      };
    });

    const requiredFields = data.filter((item: any) => {
      return item.countedAmount === undefined || item.difference === undefined;
    });

    if (requiredFields.length > 0) {
      toast('Por favor, complete los campos requeridos', 'error');
      return;
    }

    cashRegisterService()
      .closeCashRegister(id, data)
      .subscribe((response) => {
        void response;
        toast('Caja cerrada correctamente', 'success');
        navigate('/cash-register-dashboard');
      });
  };

  useEffect(() => {
    httpClient
      .get<any>(Constants.API_BASE_URL + 'cash-shifts/' + id + '/summary')
      .subscribe((response) => {
        gridRef.current?.instance().beginCustomLoading('Cargando datos...');
        gridRef.current?.instance().option('dataSource', response.data);
        const store = data.current.store() as ArrayStore;
        store.clear();
        response.data.forEach((item: any) => {
          store.insert(item);
        });
        data.current.reload();
        gridRef.current?.instance().endCustomLoading();
      });
  }, [id]);

  return (
    <div className="view datagrid-list">
      <div className={'view-wrapper view-wrapper-datagrid-list list-page'}>
        <DataGrid
          ref={gridRef}
          className="grid theme-dependent"
          dataSource={data.current}
          height={'100%'}
          showBorders={true}
          showRowLines={true}
          onRowUpdated={(e: any) => {
            e.data.updated = true;
            e.component.saveEditData();
          }}
        >
          <Editing mode="cell" allowUpdating={true} />
          <Scrolling mode="virtual" />
          <Column
            dataField="expectedAmount"
            caption="Monto esperado"
            alignment="right"
            dataType="number"
            format={{ type: 'currency', precision: 2 }}
            allowEditing={false}
          />
          <Column
            dataField="countedAmount"
            caption="Monto contado"
            alignment="right"
            dataType="number"
            format={{ type: 'currency', precision: 2 }}
          />
          <Column
            dataField="difference"
            caption={'Diferencia'}
            alignment={'right'}
            dataType={'number'}
            format={{ type: 'currency', precision: 2 }}
            allowEditing={false}
            calculateCellValue={(row: any) => {
              const diff = row.countedAmount - row.expectedAmount;
              const paymentMethodId = row.paymentMethodId;
              if (differenceData) {
                const diffData = differenceData.find(
                  (diff) => diff.paymentMethodId === paymentMethodId,
                );
                if (diffData) {
                  diffData.difference = diff;
                } else {
                  differenceData.push({ paymentMethodId: paymentMethodId, difference: diff });
                }
              } else {
                setDifferenceData([{ paymentMethodId: paymentMethodId, difference: diff }]);
              }
              return diff === diff ? diff : '';
            }}
          />
          <Column dataField="note" caption="Nota" minWidth={300} />
          <Column dataField="updated" caption="" visible={false} />
          <Toolbar>
            <Item location="before">
              <div className="grid-header">Cierre de caja</div>
            </Item>
            <Item location="after" locateInMenu="auto">
              <Button text="Cerrar caja" type="default" stylingMode="outlined" onClick={onClose} />
            </Item>
          </Toolbar>
        </DataGrid>
      </div>
    </div>
  );
};
