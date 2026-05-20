import { useRef, useState } from 'react';
import { Card } from '@shared/ui';
import { DateUtils } from '@shared/lib/DateUtils';
import { API_BASE_URL } from '@app/constants';
import './SaleConsultPage.scss';

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
  const [selectedReport, setSelectedReport] = useState('');
  const [startDate,      setStartDate]      = useState('');
  const [endDate,        setEndDate]        = useState('');
  const [loading,        setLoading]        = useState(false);
  const [error,          setError]          = useState<string | null>(null);
  const formRef = useRef<HTMLFormElement>(null);

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

    // Parse YYYY-MM-DD as local date (avoid UTC midnight → day-before bug)
    const parseLocal = (s: string) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const dateFrom = DateUtils.dateFormatter(parseLocal(startDate));
    const dateTo   = DateUtils.dateFormatter(parseLocal(endDate));
    const url      = `${API_BASE_URL}sales/report?report=${selectedReport}&date-from=${dateFrom}&date-to=${dateTo}`;

    setLoading(true);
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
    setSelectedReport('');
    setStartDate('');
    setEndDate('');
    setError(null);
    formRef.current?.reset();
  };

  return (
    <Card title="Reportes de venta">
      <form ref={formRef} className="consult-form" onSubmit={(e) => e.preventDefault()}>

        {/* Tipo de reporte */}
        <div className="consult-field">
          <label className="consult-field__label" htmlFor="report-type">
            Tipo de reporte
          </label>
          <select
            id="report-type"
            className="consult-select"
            value={selectedReport}
            onChange={(e) => { setSelectedReport(e.target.value); setError(null); }}
          >
            <option value="">Seleccionar…</option>
            {reportTypes.map((r) => (
              <option key={r.value} value={r.value}>{r.text}</option>
            ))}
          </select>
        </div>

        {/* Fecha desde */}
        <div className="consult-field">
          <label className="consult-field__label" htmlFor="date-from">
            Fecha desde
          </label>
          <input
            id="date-from"
            type="date"
            className="consult-input"
            value={startDate}
            max={endDate || undefined}
            onChange={(e) => { setStartDate(e.target.value); setError(null); }}
          />
        </div>

        {/* Fecha hasta */}
        <div className="consult-field">
          <label className="consult-field__label" htmlFor="date-to">
            Fecha hasta
          </label>
          <input
            id="date-to"
            type="date"
            className="consult-input"
            value={endDate}
            min={startDate || undefined}
            onChange={(e) => { setEndDate(e.target.value); setError(null); }}
          />
        </div>

        {/* Error */}
        {error && (
          <p className="consult-error" role="alert">{error}</p>
        )}

        {/* Acciones */}
        <div className="consult-actions">
          <button
            type="submit"
            className="app-button app-button--primary app-button--full"
            disabled={!canGenerate || loading}
            onClick={handleGenerateReport}
          >
            {loading && <span className="app-button-spinner" aria-hidden="true" />}
            {loading ? 'Generando…' : 'Generar reporte'}
          </button>
          <button
            type="button"
            className="app-icon-button"
            title="Limpiar"
            disabled={loading}
            onClick={handleClear}
          >
            <i className="ph ph-x" aria-hidden="true" />
          </button>
        </div>
      </form>

      <p className="consult-hint">
        El reporte se abrirá en una nueva pestaña en formato PDF.
        Si el navegador bloquea ventanas emergentes, permite el origen de esta aplicación.
      </p>
    </Card>
  );
};
