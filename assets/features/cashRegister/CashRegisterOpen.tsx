import { Button, Card, FormField, TextField } from '@nubitio/ui';
import { useRef, useState } from 'react';
import cashRegisterService from './services/cashRegisterService';
import { useNavigate, useParams } from 'react-router-dom';
import toast from '@shared/lib/SharedUtils';

type CashRegisterOpenParams = {
  id: string;
};

export const CashRegisterOpen = () => {
  const inputRef = useRef<HTMLInputElement>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const { id } = useParams<CashRegisterOpenParams>();
  const navigate = useNavigate();

  const openCashRegister = () => {
    const raw = inputRef.current?.value;
    const openingBalance = raw !== undefined && raw !== '' ? parseFloat(raw) : NaN;
    if (id === undefined || isNaN(openingBalance)) {
      toast('No se pudo abrir la caja', 'error');
      return;
    }
    setIsProcessing(true);
    cashRegisterService()
      .openCashRegister(id, openingBalance)
      .then((response) => {
        toast('Caja abierta', 'success');
        const cashSessionId = (response.data as { id?: number | string }).id;
        navigate('/cash-session/' + cashSessionId + '/movements');
      })
      .finally(() => {
        setIsProcessing(false);
      });
  };

  return (
    <>
      <Card title="Apertura de caja">
        <form
          style={{ width: 300 }}
          onSubmit={(e) => {
            e.preventDefault();
            openCashRegister();
          }}
        >
          <div className="mb-3">
            <FormField label="Saldo inicial" required htmlFor="cash-opening-balance">
              <TextField
                ref={inputRef}
                id="cash-opening-balance"
                type="number"
                min={0}
                step={0.01}
                required
                placeholder="0.00"
              />
            </FormField>
          </div>

          <Button
            type="submit"
            variant="success"
            fullWidth
            loading={isProcessing}
            data-testid="cash-open-submit-btn"
          >
            {isProcessing ? 'Procesando...' : 'Abrir caja'}
          </Button>
        </form>
      </Card>
    </>
  );
};
