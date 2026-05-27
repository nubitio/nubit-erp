import { useEffect, useRef, useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
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
  const [isClosing, setIsClosing] = useState(false);
  const navigate = useNavigate();

  const closeCashRegister = () => {
    const closureData = gridRef.current?.getRows();
    const differences = gridRef.current?.getDifferences() ?? [];
    if (id === undefined || closureData === undefined) {
      toast('Error al cerrar la caja', 'error');
      return;
    }

    const data = closureData.map((item) => ({
      cashShift: API + 'cash-shifts/' + id,
      paymentMethod: API + 'payment-methods/' + item.paymentMethodId,
      expectedAmount: Number(item.expectedAmount),
      countedAmount: item.countedAmount,
      difference: differences.find((diff) => diff.paymentMethodId === item.paymentMethodId)
        ?.difference,
      note: item.note,
    }));

    const incomplete = data.some(
      (item) => item.countedAmount === undefined || item.difference === undefined,
    );
    if (incomplete) {
      toast('Por favor, complete los campos requeridos', 'error');
      return;
    }

    setIsClosing(true);
    cashRegisterService()
      .closeCashRegister(id, data)
      .then(() => {
        toast('Caja cerrada correctamente', 'success');
        navigate('/cashbox');
      })
      .catch(() => toast('No se pudo cerrar la caja', 'error'))
      .finally(() => setIsClosing(false));
  };

  useEffect(() => {
    gridRef.current?.showLoading('Cargando datos...');
    httpClient
      .get<CashClosureRow[]>(API + 'cash-shifts/' + id + '/summary')
      .then((response) => {
        gridRef.current?.setRows(response.data);
      })
      .catch(() => toast('No se pudo cargar el resumen de la caja', 'error'))
      .finally(() => {
        gridRef.current?.hideLoading();
      });
  }, [id]);

  return (
    <div className="cash-close-page">
      <CashClosureGrid
        ref={gridRef}
        closing={isClosing}
        onCloseRequest={() => setShowCloseConfirm(true)}
      />
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
