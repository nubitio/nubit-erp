import DateBox from 'devextreme-react/date-box';
import { Button, LoadIndicator } from 'devextreme-react';
import { useRef, useState } from 'react';
import Form, { FormRef, SimpleItem } from 'devextreme-react/form';
import { Card } from '@shared/ui';
import SelectBox from 'devextreme-react/select-box';
import { DateUtils } from '@shared/lib/DateUtils';
import { Constants } from '@core/common/Constants';

const reportTypes = [
  { value: 'sale-by-product',      text: 'Ventas por producto' },
  { value: 'detailed-sales',       text: 'Ventas detalladas' },
  { value: 'accounting-report',    text: 'Reporte contable' },
  { value: 'payment-method-daily', text: 'Pagos diarios por método' },
  { value: 'daily-summary',        text: 'Resumen diario de ventas' },
  { value: 'voided-sales',         text: 'Documentos anulados' },
  { value: 'credit-notes',         text: 'Notas de crédito' },
];

export const SaleConsultPage = () => {
  const [selectedReport, setSelectedReport] = useState<string | undefined>();
  const [startDate,      setStartDate]      = useState<Date | undefined>();
  const [endDate,        setEndDate]        = useState<Date | undefined>();
  const [loading,        setLoading]        = useState(false);
  const [error,          setError]          = useState<string | null>(null);
  const formRef = useRef<FormRef>(null);

  const canGenerate = !!selectedReport && !!startDate && !!endDate;

  const handleGenerateReport = () => {
    setError(null);

    if (!selectedReport) {
      setError('Selecciona un tipo de reporte.');
      return;
    }
    if (!startDate || !endDate) {
      setError('Selecciona el rango de fechas.');
      return;
    }
    if (startDate > endDate) {
      setError('La fecha "desde" no puede ser posterior a la fecha "hasta".');
      return;
    }

    const dateFrom = DateUtils.dateFormatter(startDate);
    const dateTo   = DateUtils.dateFormatter(endDate);
    const url      = `${Constants.API_BASE_URL}sales/report?report=${selectedReport}&date-from=${dateFrom}&date-to=${dateTo}`;

    setLoading(true);

    // window.open is synchronous — simulate a brief loading state so the user
    // gets visual feedback before the browser hands off to the new tab.
    const win = window.open(url);
    setTimeout(() => {
      setLoading(false);
      if (!win) {
        setError(
          'El reporte no pudo abrirse. Verifica que el navegador no esté bloqueando ventanas emergentes.',
        );
      }
    }, 800);
  };

  const handleClear = () => {
    setSelectedReport(undefined);
    setStartDate(undefined);
    setEndDate(undefined);
    setError(null);
    formRef.current?.instance().reset();
  };

  return (
    <Card title="Reportes de venta">
      <Form ref={formRef} width={320} labelMode="floating">
        <SimpleItem label={{ text: 'Tipo de reporte' }}>
          <SelectBox
            dataSource={reportTypes}
            displayExpr="text"
            valueExpr="value"
            value={selectedReport}
            stylingMode="outlined"
            placeholder="Seleccionar…"
            showClearButton
            onValueChanged={(e) => {
              setSelectedReport(e.value ?? undefined);
              setError(null);
            }}
          />
        </SimpleItem>

        <SimpleItem label={{ text: 'Fecha desde' }}>
          <DateBox
            value={startDate}
            stylingMode="outlined"
            displayFormat="dd/MM/yyyy"
            showClearButton
            max={endDate}
            onValueChanged={(e) => {
              setStartDate(e.value ?? undefined);
              setError(null);
            }}
          />
        </SimpleItem>

        <SimpleItem label={{ text: 'Fecha hasta' }}>
          <DateBox
            value={endDate}
            stylingMode="outlined"
            displayFormat="dd/MM/yyyy"
            showClearButton
            min={startDate}
            onValueChanged={(e) => {
              setEndDate(e.value ?? undefined);
              setError(null);
            }}
          />
        </SimpleItem>

        {error && (
          <SimpleItem>
            <p style={{ color: 'var(--dx-color-danger, #d9534f)', fontSize: 13, margin: '4px 0 0' }}>
              {error}
            </p>
          </SimpleItem>
        )}

        <SimpleItem>
          <div style={{ display: 'flex', gap: 8 }}>
            <Button
              text={loading ? 'Generando…' : 'Generar reporte'}
              type="default"
              stylingMode="contained"
              width="100%"
              disabled={!canGenerate || loading}
              onClick={handleGenerateReport}
            >
              {loading && (
                <LoadIndicator height={16} width={16} visible style={{ marginRight: 6 }} />
              )}
            </Button>
            <Button
              icon="clear"
              type="normal"
              stylingMode="outlined"
              hint="Limpiar"
              onClick={handleClear}
              disabled={loading}
            />
          </div>
        </SimpleItem>
      </Form>

      <p style={{ marginTop: 16, fontSize: 12, color: '#888', maxWidth: 320 }}>
        El reporte se abrirá en una nueva pestaña en formato PDF.
        Si el navegador bloquea ventanas emergentes, permite el origen de esta aplicación.
      </p>
    </Card>
  );
};
