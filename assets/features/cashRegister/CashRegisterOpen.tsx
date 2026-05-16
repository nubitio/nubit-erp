import { Card } from '@shared/ui';
import Form, { FormRef, SimpleItem } from 'devextreme-react/form';
import { useRef, useState } from 'react';
import { currencyField } from '@core/field/FieldBuilders';
import { buildField } from '@core/form/FormUtils';
import cashRegisterService from './services/cashRegisterService';
import { useNavigate, useParams } from 'react-router-dom';
import toast from '@shared/lib/SharedUtils';

type CashRegisterOpenParams = {
  id: string;
};

export const CashRegisterOpen = () => {
  const formRef = useRef<FormRef>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const { id } = useParams<CashRegisterOpenParams>();
  const navigate = useNavigate();

  const openingBalanceField = currencyField()
    .name('openingBalance')
    .label('Saldo inicial')
    .required(true)
    .build();

  const openCashRegister = () => {
    const formData = formRef.current?.instance().option('formData');
    if (id === undefined || formData === undefined) {
      toast('No se pudo abrir la caja', 'error');
      return;
    }
    setIsProcessing(true);
    cashRegisterService()
      .openCashRegister(id, formData.openingBalance)
      .then((response) => {
        toast('Caja abierta', 'success');
        const cashShiftId = response.data.id;
        navigate('/cash-shift/' + cashShiftId + '/movements');
      })
      .finally(() => {
        setIsProcessing(false);
      });
  };

  return (
    <>
      <Card title="Apertura de caja">
        <Form ref={formRef} width={300}>
          {buildField(openingBalanceField, openingBalanceField.name)}

          <SimpleItem>
            <button
              type="button"
              className="app-button app-button--success app-button--full"
              disabled={isProcessing}
              data-testid="cash-open-submit-btn"
              onClick={openCashRegister}
            >
              {isProcessing && <span className="app-button-spinner" aria-hidden="true" />}
              {isProcessing ? 'Procesando...' : 'Abrir caja'}
            </button>
          </SimpleItem>
        </Form>
      </Card>
    </>
  );
};
