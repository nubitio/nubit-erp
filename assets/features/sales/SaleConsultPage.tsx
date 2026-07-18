import { useCallback, useMemo, useRef, useState } from 'react';
import { AppDropdown } from '@nubitio/ui';
import {
  ColumnChooserButton,
  DataGridView,
  useColumnChooser,
  type DataGridViewOptions,
  type GridHandle,
  type ResourceConfig,
} from '@nubitio/crud';
import { useCoreTranslation } from '@nubitio/core';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { SaleFields } from './SaleFields';
import { saleEndpoints } from './api/saleEndpoints';

import './SaleConsultPage.scss';

const API = getCoreApiBaseUrl();

const reportTypes = [
  { value: 'detailed-sales', text: 'Ventas por comprobante' },
  { value: 'sales-by-line', text: 'Ventas por línea' },
  { value: 'sale-by-product', text: 'Ventas por producto' },
  { value: 'accounting-report', text: 'Reporte contable' },
  { value: 'payment-method-daily', text: 'Pagos diarios por método' },
  { value: 'daily-summary', text: 'Resumen diario de ventas' },
  { value: 'voided-sales', text: 'Documentos anulados' },
  { value: 'credit-notes', text: 'Notas de crédito' },
];

const SALE_CONSULT_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['issuedDate', 'customer', 'paymentMethod', 'grandTotal', 'status'],
  },
  {
    key: 'full',
    label: 'Completo',
    columns: [
      'issuedDate',
      'customer',
      'invoiceFullNumber',
      'paymentMethod',
      'currency',
      'grandTotal',
      'status',
    ],
  },
  {
    key: 'accounting',
    label: 'Contable',
    columns: ['issuedDate', 'customer', 'invoiceFullNumber', 'currency', 'grandTotal', 'status'],
  },
];

const SALE_CONSULT_PRESET_RESOURCE: ResourceConfig = {
  id: 'sales-consult',
  title: 'Consulta ventas',
  apiUrl: '',
  defaultPreset: 'basic',
  columnPresets: SALE_CONSULT_COLUMN_PRESETS,
};

function datePart(value: unknown): string {
  return String(value ?? '').slice(0, 10);
}

function collectFilterRules(filter: unknown): unknown[][] {
  if (!Array.isArray(filter)) return [];
  if (filter.length >= 3 && typeof filter[0] === 'string') return [filter];

  return filter.flatMap((item) => collectFilterRules(item));
}

function extractIssuedDateRange(filter: unknown[]): { from: string; to: string } {
  let from = '';
  let to = '';

  collectFilterRules(filter).forEach(([field, operator, value]) => {
    if (field !== 'issuedDate') return;

    const date = datePart(value);
    if (!date) return;

    if (operator === '=') {
      from = date;
      to = date;
    } else if (operator === '>=') {
      from = date;
    } else if (operator === '<=') {
      to = date;
    }
  });

  return { from, to };
}

function getFilenameFromDisposition(disposition: string | undefined): string {
  if (!disposition) return 'reporte_ventas.xlsx';

  const utf8Match = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
  if (utf8Match?.[1]) return decodeURIComponent(utf8Match[1].replace(/"/g, ''));

  const match = /filename="?([^";]+)"?/i.exec(disposition);
  return match?.[1] ?? 'reporte_ventas.xlsx';
}

function downloadBlob(blob: Blob, filename: string): void {
  const objectUrl = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = objectUrl;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(objectUrl);
}

export const SaleConsultPage = () => {
  const { t } = useCoreTranslation();
  const gridRef = useRef<GridHandle>(null);
  const [selectedReport, setSelectedReport] = useState('detailed-sales');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fields = useMemo(() => SaleFields(null), []);
  const columnChooserState = useColumnChooser(SALE_CONSULT_PRESET_RESOURCE, fields);
  const canGenerate = !!selectedReport;

  const handleGenerateReport = useCallback(async () => {
    setError(null);

    if (!selectedReport) {
      setError('Selecciona un tipo de reporte.');
      return;
    }
    const gridFilter = gridRef.current?.getFilter() ?? [];
    const dateRange = extractIssuedDateRange(gridFilter);
    if (dateRange.from && dateRange.to && dateRange.from > dateRange.to) {
      setError('La fecha "desde" no puede ser posterior a la fecha "hasta".');
      return;
    }

    const params = new URLSearchParams({
      report: selectedReport,
    });
    if (gridFilter.length > 0) params.set('filter', JSON.stringify(gridFilter));
    if (dateRange.from) params.set('date-from', dateRange.from);
    if (dateRange.to) params.set('date-to', dateRange.to);
    const url = `${API}sales/report?${params.toString()}`;

    setLoading(true);
    try {
      const response = await httpClient.get<Blob>(url, { responseType: 'blob' });
      downloadBlob(response.data, getFilenameFromDisposition(response.headers['content-disposition']));
    } catch (err) {
      const message = err instanceof Error
        ? err.message
        : 'No se pudo exportar el reporte.';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [selectedReport]);

  const handleClear = useCallback(() => {
    setSelectedReport('detailed-sales');
    setError(null);
    gridRef.current?.reset();
  }, []);

  const toolbar = useMemo<DataGridViewOptions['toolbar']>(() => ({
    primary: [
      {
        text: loading ? 'Generando...' : 'Exportar XLSX',
        icon: 'ph-download-simple',
        type: 'primary',
        onClick: handleGenerateReport,
        disabled: !canGenerate || loading,
      },
    ],
    utility: [
      {
        text: 'Limpiar',
        icon: 'ph-x',
        onClick: handleClear,
        disabled: loading,
      },
    ],
    showRefresh: true,
  }), [canGenerate, handleClear, handleGenerateReport, loading]);

  const beforeToolbar = useCallback(() => (
    <>
      <AppDropdown
        id="sales-consult-report"
        label="Reporte"
        icon="ph-chart-bar"
        value={selectedReport}
        options={reportTypes.map((report) => ({ value: report.value, label: report.text }))}
        disabled={loading}
        variant="toolbar"
        showFieldLabel={false}
        onChange={(value) => {
          setSelectedReport(value);
          setError(null);
        }}
      />
      <ColumnChooserButton
        state={columnChooserState}
        triggerLabel={t('crudPage.columnChooserLabel')}
        selectAllLabel={t('crudPage.columnChooserSelectAll')}
        resetLabel={t('crudPage.columnChooserReset')}
      />
    </>
  ), [loading, columnChooserState, selectedReport, t]);

  return (
    <div className="sale-consult-page">
      <DataGridView
        id="sales-consult"
        ref={gridRef}
        title="Consulta ventas"
        url={saleEndpoints.list()}
        fields={fields}
        sort={[{ selector: 'issuedDate', desc: true }]}
        toolbar={toolbar}
        beforeToolbar={beforeToolbar}
        allowAdd={false}
        allowEdit={false}
        allowDelete={false}
        selectionMode="single"
        visibleColumns={columnChooserState.visibleColumns}
        errorMessage={error}
        onDismissError={() => setError(null)}
      />
    </div>
  );
};
