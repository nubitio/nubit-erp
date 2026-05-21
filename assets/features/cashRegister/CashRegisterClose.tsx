import { useEffect, useRef, useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import { API_BASE_URL } from '@app/constants';
import { useNavigate, useParams } from 'react-router-dom';
import toast from '@shared/lib/SharedUtils';
import cashRegisterService from './services/cashRegisterService';
import { ConfirmDialog } from '@nubit/ui';
import {
  CashClosureGrid,
  type CashClosureGridHandle,
  type CashClosureRow,
} from './components/CashClosureGrid';
import './CashRegisterClose.scss';

type CashClosureParams = {
  id: string;
};

export const CashRegisterClose = () => {
  const { id } = useParams<CashClosureParams>();
  const gridRef = useRef<CashClosureGridHandle | null>(null);
  const [showCloseConfirm, setShowCloseConfirm] = useState(false);
  const navigate = useNavigate();

  const onClose = () => {
    setShowCloseConfirm(true);
  };

  const closeCashRegister = () => {
    const closureData = gridRef.current?.getRows();
    const differences = gridRef.current?.getDifferences() ?? [];
    if (id === undefined || closureData === undefined) {
      toast('Error al cerrar la caja', 'error');
      return;
    }
    const data = closureData.map((item: any) => {
      return {
        cashShift: API_BASE_URL + 'cash-shifts/' + id,
        paymentMethod: API_BASE_URL + 'payment-methods/' + item.paymentMethodId,
        expectedAmount: Number(item.expectedAmount),
        countedAmount: item.countedAmount,
        difference: differences.find((diff) => diff.paymentMethodId === item.paymentMethodId)
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
      .then((response) => {
        void response;
        toast('Caja cerrada correctamente', 'success');
        navigate('/cashbox');
      });
  };

  useEffect(() => {
    httpClient
      .get<any>(API_BASE_URL + 'cash-shifts/' + id + '/summary')
      .then((response) => {
        gridRef.current?.showLoading('Cargando datos...');
        gridRef.current?.setRows(response.data as CashClosureRow[]);
        gridRef.current?.hideLoading();
      });
  }, [id]);

  return (
    <div className="view datagrid-list">
      <div className={'view-wrapper view-wrapper-datagrid-list list-page'}>
        <CashClosureGrid
          ref={gridRef}
          onCloseRequest={onClose}
        />
      </div>
      <ConfirmDialog
        open={showCloseConfirm}
        title="Cerrar caja"
        message="¿Está seguro que desea cerrar la caja?"
        cancelLabel="No"
        confirmLabel="Sí"
        onCancel={() => setShowCloseConfirm(false)}
        onConfirm={() => {
          setShowCloseConfirm(false);
          closeCashRegister();
        }}
      />
    </div>
  );
};
