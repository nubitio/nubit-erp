import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button, FileDropzone } from '@nubitio/ui';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { extractCollection } from '@shared/pos/helpers';
import { ProductImportDialog } from '@features/product/components/ProductImportDialog';
import { pick } from './parseCsv';
import './ExcelImportPage.scss';

const API = getCoreApiBaseUrl();

type Step = 'products' | 'customers' | 'balances' | 'done';
type RowResult = { row: number; ok: boolean; message: string };
type BalanceKind = 'receivables' | 'payables';

const STEPS: { key: Step; title: string; subtitle: string }[] = [
  { key: 'products', title: 'Productos', subtitle: 'Código, nombre, precio, stock mínimo.' },
  { key: 'customers', title: 'Clientes', subtitle: 'RUC/DNI, nombre y teléfono.' },
  { key: 'balances', title: 'Saldos', subtitle: 'Por cobrar y por pagar.' },
  { key: 'done', title: 'Listo', subtitle: 'Ya puedes vender y cobrar en un solo sistema.' },
];

async function spreadsheetRows(file: File): Promise<Array<Record<string, string>>> {
  const formData = new FormData();
  formData.append('file', file);
  const response = await httpClient.post<{ rows: Array<Record<string, string>> }>(
    '/api/v1/onboarding/spreadsheets/rows',
    formData,
  );
  return response.data.rows;
}

export function ExcelImportPage() {
  const navigate = useNavigate();
  const [step, setStep] = useState<Step>('products');
  const [productImportOpen, setProductImportOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [results, setResults] = useState<RowResult[]>([]);
  const [customerFile, setCustomerFile] = useState<File | null>(null);
  const [payableFile, setPayableFile] = useState<File | null>(null);
  const [receivableFile, setReceivableFile] = useState<File | null>(null);
  const [payablePreview, setPayablePreview] = useState<Array<Record<string, string>> | null>(null);
  const [receivablePreview, setReceivablePreview] = useState<Array<Record<string, string>> | null>(
    null,
  );
  const [previewError, setPreviewError] = useState<string | null>(null);

  const stepIndex = STEPS.findIndex((item) => item.key === step);
  const summary = useMemo(() => {
    const ok = results.filter((row) => row.ok).length;
    const failed = results.filter((row) => !row.ok).length;
    return { ok, failed };
  }, [results]);

  const importCustomers = async () => {
    if (!customerFile) return;
    setBusy(true);
    let rows: Array<Record<string, string>>;
    try {
      rows = await spreadsheetRows(customerFile);
    } catch (error) {
      setResults([
        {
          row: 0,
          ok: false,
          message: error instanceof Error ? error.message : 'No se pudo leer el Excel',
        },
      ]);
      setBusy(false);
      return;
    }
    const next: RowResult[] = [];
    for (const [index, row] of rows.entries()) {
      const ruc = pick(row, ['ruc', 'ruc/dni', 'dni', 'documento']);
      const businessName = pick(row, ['nombre', 'razon social', 'razón social', 'cliente']);
      const phone = pick(row, ['telefono', 'teléfono', 'phone']);
      const email = pick(row, ['correo', 'email']);
      if (!ruc || !businessName) {
        next.push({ row: index + 2, ok: false, message: 'Falta RUC/DNI o nombre' });
        continue;
      }
      try {
        await httpClient.post(`${API}customers`, {
          ruc,
          businessName,
          phone: phone || null,
          email: email || null,
          isActive: true,
        });
        next.push({ row: index + 2, ok: true, message: businessName });
      } catch (error) {
        next.push({
          row: index + 2,
          ok: false,
          message: error instanceof Error ? error.message : 'No se pudo crear el cliente',
        });
      }
    }
    setResults(next);
    setBusy(false);
  };

  const importReceivables = async () => {
    if (!receivableFile) return;
    setBusy(true);
    let rows = receivablePreview;
    if (rows === null) {
      try {
        rows = await spreadsheetRows(receivableFile);
        setReceivablePreview(rows);
        setPreviewError(null);
        setBusy(false);
        return;
      } catch (error) {
        setPreviewError(error instanceof Error ? error.message : 'No se pudo leer el Excel');
        setBusy(false);
        return;
      }
    }
    try {
      const response = await httpClient.post<{
        created: number;
        skipped?: number;
        errors: Array<{ row: number; message: string }>;
      }>('/api/v1/onboarding/receivables', { rows });
      const payload = response.data;
      const next: RowResult[] = (payload.errors ?? []).map((error) => ({
        row: error.row,
        ok: false,
        message: error.message,
      }));
      if ((payload.skipped ?? 0) > 0) {
        next.unshift({
          row: 0,
          ok: true,
          message: `${payload.skipped} filas ya importadas (omitidas)`,
        });
      }
      if (payload.created > 0) {
        next.unshift({
          row: 0,
          ok: true,
          message: `${payload.created} saldos por cobrar importados`,
        });
      }
      setResults(next.length > 0 ? next : [{ row: 0, ok: true, message: 'Sin filas nuevas' }]);
    } catch (error) {
      setResults([
        {
          row: 0,
          ok: false,
          message:
            error instanceof Error
              ? error.message
              : 'No se pudieron importar las cuentas por cobrar',
        },
      ]);
    }
    setBusy(false);
  };

  const importPayables = async () => {
    if (!payableFile) return;
    setBusy(true);
    let rows = payablePreview;
    if (rows === null) {
      try {
        rows = await spreadsheetRows(payableFile);
        setPayablePreview(rows);
        setPreviewError(null);
        setBusy(false);
        return;
      } catch (error) {
        setPreviewError(error instanceof Error ? error.message : 'No se pudo leer el Excel');
        setBusy(false);
        return;
      }
    }
    const suppliers = extractCollection<{ id?: string; ruc?: string; '@id'?: string }>(
      (await httpClient.get(`${API}suppliers?pagination=false`)).data,
    );
    const categories = extractCollection<{ id?: string; '@id'?: string }>(
      (await httpClient.get(`${API}expense-categories?pagination=false`)).data,
    );
    const branches = extractCollection<{ id?: string; '@id'?: string }>(
      (await httpClient.get(`${API}branches?pagination=false`)).data,
    );
    const categoryIri =
      categories[0]?.['@id'] ??
      (categories[0]?.id ? `${API}expense-categories/${categories[0].id}` : null);
    const branchIri =
      branches[0]?.['@id'] ?? (branches[0]?.id ? `${API}branches/${branches[0].id}` : null);
    const next: RowResult[] = [];

    for (const [index, row] of rows.entries()) {
      const ruc = pick(row, ['ruc', 'ruc proveedor', 'proveedor ruc']);
      const supplierName = pick(row, ['proveedor', 'razon social', 'razón social']);
      const amount = pick(row, ['monto', 'importe', 'total', 'saldo']);
      const due = pick(row, ['vencimiento', 'fecha de vencimiento', 'due']);
      const number = pick(row, ['numero', 'número', 'factura']);
      const supplier = suppliers.find((item) => item.ruc === ruc);
      if (!supplier || !categoryIri || !branchIri || !amount) {
        next.push({
          row: index + 2,
          ok: false,
          message: 'Falta proveedor (RUC), monto o datos de empresa',
        });
        continue;
      }
      try {
        const dueDate = due || new Date().toISOString().slice(0, 10);
        await httpClient.post(`${API}supplier-bills`, {
          supplier: supplier['@id'] ?? `${API}suppliers/${supplier.id}`,
          branch: branchIri,
          category: categoryIri,
          amount: Number(amount.replace(',', '.')),
          dueDate,
          issueDate: dueDate,
          number: number || undefined,
          currency: 'PEN',
        });
        next.push({ row: index + 2, ok: true, message: supplierName || ruc });
      } catch (error) {
        next.push({
          row: index + 2,
          ok: false,
          message: error instanceof Error ? error.message : 'No se pudo registrar el saldo',
        });
      }
    }
    setResults(next);
    setBusy(false);
  };

  return (
    <div className="excel-import">
      <header className="excel-import__header">
        <div>
          <p className="excel-import__kicker">Salir de Excel</p>
          <h1>Trae tus datos al mini ERP</h1>
          <p>
            Migración inicial desde Excel: productos, clientes y saldos abiertos. Úsala al comenzar
            o al cambiar de sistema.
          </p>
        </div>
        <Button variant="secondary" onClick={() => navigate('/dashboard')}>
          Volver al inicio
        </Button>
      </header>

      <ol className="excel-import__steps">
        {STEPS.map((item, index) => (
          <li
            key={item.key}
            className={index === stepIndex ? 'is-active' : index < stepIndex ? 'is-done' : ''}
          >
            <strong>
              {index + 1}. {item.title}
            </strong>
            <span>{item.subtitle}</span>
          </li>
        ))}
      </ol>

      {step === 'products' && (
        <section className="excel-import__card">
          <h2>1. Productos</h2>
          <p>
            Usa la plantilla oficial con categorías y unidades. Es el mismo importador del catálogo.
          </p>
          <div className="excel-import__actions">
            <Button onClick={() => setProductImportOpen(true)}>Importar productos</Button>
            <Button variant="secondary" onClick={() => setStep('customers')}>
              Ya los tengo / Siguiente
            </Button>
          </div>
        </section>
      )}

      {step === 'customers' && (
        <section className="excel-import__card">
          <h2>2. Clientes</h2>
          <p>
            Sube el Excel que ya usas. Solo debe tener las columnas RUC/DNI, Nombre, Teléfono y
            Correo.
          </p>
          <div className="excel-import__actions">
            <Button
              variant="secondary"
              onClick={() =>
                window.open('/api/v1/onboarding/spreadsheets/template/customers', '_blank')
              }
            >
              Descargar plantilla
            </Button>
          </div>
          <FileDropzone
            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
            value={customerFile ? { fileName: customerFile.name } : null}
            labels={{
              prompt: 'Suelta tu Excel de clientes o haz clic para buscarlo',
              hint: 'Formato .xlsx · un cliente por fila.',
              replace: 'Cambiar archivo',
              remove: 'Quitar',
              open: 'Abrir',
            }}
            onFileSelect={setCustomerFile}
            onClear={() => setCustomerFile(null)}
          />
          <div className="excel-import__actions">
            <Button onClick={() => void importCustomers()} disabled={!customerFile || busy}>
              {busy ? 'Importando…' : 'Importar clientes'}
            </Button>
            <Button
              variant="secondary"
              onClick={() => {
                setResults([]);
                setStep('balances');
              }}
            >
              Siguiente
            </Button>
          </div>
        </section>
      )}

      {step === 'balances' && (
        <section className="excel-import__card">
          <h2>3. Migración de saldos iniciales</h2>
          <p>
            Importa desde Excel lo que te deben y lo que debes. Primero revisa la vista previa; nada
            se registra hasta confirmar.
          </p>

          <h3>Por cobrar</h3>
          <div className="excel-import__actions">
            <Button
              variant="secondary"
              onClick={() =>
                window.open('/api/v1/onboarding/spreadsheets/template/receivables', '_blank')
              }
            >
              Plantilla por cobrar
            </Button>
          </div>
          <FileDropzone
            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
            value={receivableFile ? { fileName: receivableFile.name } : null}
            labels={{
              prompt: 'Excel de cuentas por cobrar',
              hint: 'RUC, nombre, monto y vencimiento. No emite SUNAT: es un saldo inicial.',
              replace: 'Cambiar archivo',
              remove: 'Quitar',
              open: 'Abrir',
            }}
            onFileSelect={(file) => {
              setReceivableFile(file);
              setReceivablePreview(null);
              setPreviewError(null);
            }}
            onClear={() => {
              setReceivableFile(null);
              setReceivablePreview(null);
            }}
          />
          {receivablePreview && <BalancePreview kind="receivables" rows={receivablePreview} />}
          {previewError && (
            <p className="excel-import__error" role="alert">
              {previewError}
            </p>
          )}
          <div className="excel-import__actions">
            <Button onClick={() => void importReceivables()} disabled={!receivableFile || busy}>
              {busy
                ? 'Procesando…'
                : receivablePreview
                  ? 'Confirmar por cobrar'
                  : 'Previsualizar por cobrar'}
            </Button>
          </div>

          <h3>Por pagar</h3>
          <div className="excel-import__actions">
            <Button
              variant="secondary"
              onClick={() =>
                window.open('/api/v1/onboarding/spreadsheets/template/payables', '_blank')
              }
            >
              Plantilla por pagar
            </Button>
          </div>
          <FileDropzone
            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
            value={payableFile ? { fileName: payableFile.name } : null}
            labels={{
              prompt: 'Excel de cuentas por pagar',
              hint: 'El proveedor debe existir en Catálogo → Proveedores.',
              replace: 'Cambiar archivo',
              remove: 'Quitar',
              open: 'Abrir',
            }}
            onFileSelect={(file) => {
              setPayableFile(file);
              setPayablePreview(null);
              setPreviewError(null);
            }}
            onClear={() => {
              setPayableFile(null);
              setPayablePreview(null);
            }}
          />
          {payablePreview && <BalancePreview kind="payables" rows={payablePreview} />}
          <div className="excel-import__actions">
            <Button onClick={() => void importPayables()} disabled={!payableFile || busy}>
              {busy
                ? 'Procesando…'
                : payablePreview
                  ? 'Confirmar por pagar'
                  : 'Previsualizar por pagar'}
            </Button>
            <Button
              variant="secondary"
              onClick={() => {
                setResults([]);
                setStep('done');
              }}
            >
              Siguiente
            </Button>
          </div>
        </section>
      )}

      {step === 'done' && (
        <section className="excel-import__card">
          <h2>4. Checklist de la primera semana</h2>
          <ol className="excel-import__checklist">
            <li>
              <button type="button" onClick={() => navigate('/config/company')}>
                Revisa razón social y RUC
              </button>
            </li>
            <li>
              <button type="button" onClick={() => navigate('/config/series')}>
                Confirma series SUNAT
              </button>
            </li>
            <li>
              <button type="button" onClick={() => navigate('/catalog/products')}>
                Abre el catálogo y verifica precios
              </button>
            </li>
            <li>
              <button type="button" onClick={() => navigate('/pos')}>
                Haz la primera venta
              </button>
            </li>
          </ol>
          <Button onClick={() => navigate('/dashboard')}>Ir al inicio</Button>
        </section>
      )}

      {results.length > 0 && (
        <section className="excel-import__results">
          <p>
            {summary.ok} ok · {summary.failed} con error
          </p>
          <ul>
            {results.slice(0, 20).map((row) => (
              <li key={`${row.row}-${row.message}`} className={row.ok ? 'is-ok' : 'is-error'}>
                Fila {row.row}: {row.message}
              </li>
            ))}
          </ul>
        </section>
      )}

      <ProductImportDialog
        isOpen={productImportOpen}
        onClose={() => setProductImportOpen(false)}
        onImportDone={() => setStep('customers')}
      />
    </div>
  );
}

function BalancePreview({
  kind,
  rows,
}: {
  kind: BalanceKind;
  rows: Array<Record<string, string>>;
}) {
  const columns =
    kind === 'receivables'
      ? ['ruc/dni', 'nombre', 'monto', 'vencimiento', 'numero']
      : ['ruc', 'proveedor', 'monto', 'vencimiento', 'numero'];
  const visibleRows = rows.slice(0, 8);
  const issues = rows.filter((row) => {
    const identity = pick(
      row,
      kind === 'receivables'
        ? ['ruc', 'ruc/dni', 'dni', 'documento']
        : ['ruc', 'ruc proveedor', 'proveedor ruc'],
    );
    const name = pick(
      row,
      kind === 'receivables'
        ? ['nombre', 'razon social', 'razón social', 'cliente']
        : ['proveedor', 'razon social', 'razón social'],
    );
    const amount = pick(row, ['monto', 'importe', 'total', 'saldo']);
    return !identity || !name || !amount || Number(amount.replace(',', '.')) <= 0;
  }).length;

  return (
    <div
      className="excel-import__preview"
      aria-label={`Vista previa ${kind === 'receivables' ? 'por cobrar' : 'por pagar'}`}
    >
      <strong>Vista previa · {rows.length} filas</strong>
      <p className="excel-import__preview-effect">
        Efecto al confirmar:{' '}
        {kind === 'receivables'
          ? 'Dr 121 Cuentas por cobrar · Cr 591 Saldos iniciales'
          : 'Dr 659 Gasto pendiente · Cr 421 Cuentas por pagar'}
        .
      </p>
      {issues > 0 && (
        <p className="excel-import__preview-warning" role="alert">
          {issues} filas tienen datos obligatorios incompletos o un monto inválido. Corrígelas antes
          de confirmar.
        </p>
      )}
      <div className="excel-import__preview-table-wrap">
        <table className="excel-import__preview-table">
          <thead>
            <tr>
              {columns.map((column) => (
                <th key={column}>{column}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {visibleRows.map((row, index) => (
              <tr key={`${index}-${row[columns[0]] ?? ''}`}>
                {columns.map((column) => (
                  <td key={column}>{row[column] || '—'}</td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {rows.length > visibleRows.length && (
        <small>
          Se muestran las primeras {visibleRows.length} filas. Se importarán {rows.length}.
        </small>
      )}
    </div>
  );
}
