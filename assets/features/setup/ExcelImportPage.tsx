import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Alert,
  Badge,
  Button,
  Card,
  Cluster,
  DataTable,
  type DataTableColumn,
  FileDropzone,
  PageHeader,
  Progress,
  Row,
  Stack,
  Text,
  Timeline,
  TimelineItem,
} from '@nubitio/ui';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { extractCollection } from '@shared/pos/helpers';
import toast from '@shared/lib/SharedUtils';
import { downloadProductImportTemplate } from '@features/product/model/downloadProductImportTemplate';
import { progressPercent, useProductImportJob } from '@features/product/model/useProductImportJob';
import { downloadTemplate } from './downloadTemplate';
import { pick } from './parseCsv';

const API = getCoreApiBaseUrl();
const XLSX = '.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
const TEMPLATE_URL = '/api/v1/onboarding/spreadsheets/template';

type Step = 'products' | 'customers' | 'receivables' | 'payables' | 'done';
type RowResult = { row: number; ok: boolean; message: string };
type BalanceKind = 'receivables' | 'payables';

const STEPS: { key: Step; title: string; subtitle: string }[] = [
  { key: 'products', title: 'Productos', subtitle: 'Catálogo y precios' },
  { key: 'customers', title: 'Clientes', subtitle: 'RUC/DNI y contacto' },
  { key: 'receivables', title: 'Por cobrar', subtitle: 'Lo que te deben' },
  { key: 'payables', title: 'Por pagar', subtitle: 'Lo que debes' },
  { key: 'done', title: 'Listo', subtitle: 'Primeros pasos' },
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

const downloadSafely = (download: () => Promise<void>) => () =>
  download().catch((error: unknown) =>
    toast(error instanceof Error ? error.message : 'No se pudo descargar la plantilla.', 'error'),
  );

/**
 * First-run import wizard, shown once after the tenant onboarding: one step per kind of data, each
 * with the same two moves (download the template, upload the filled file).
 */
export function ExcelImportPage() {
  const navigate = useNavigate();
  const [step, setStep] = useState<Step>('products');
  const [busy, setBusy] = useState(false);
  const [results, setResults] = useState<RowResult[]>([]);
  const [productFile, setProductFile] = useState<File | null>(null);
  const [customerFile, setCustomerFile] = useState<File | null>(null);
  const [payableFile, setPayableFile] = useState<File | null>(null);
  const [receivableFile, setReceivableFile] = useState<File | null>(null);
  const [payablePreview, setPayablePreview] = useState<Array<Record<string, string>> | null>(null);
  const [receivablePreview, setReceivablePreview] = useState<Array<Record<string, string>> | null>(
    null,
  );
  const [previewError, setPreviewError] = useState<string | null>(null);
  const productJob = useProductImportJob();

  const stepIndex = STEPS.findIndex((item) => item.key === step);
  const summary = useMemo(() => {
    const ok = results.filter((row) => row.ok).length;
    const failed = results.filter((row) => !row.ok).length;
    return { ok, failed };
  }, [results]);

  const goTo = (next: Step) => {
    setResults([]);
    setPreviewError(null);
    setStep(next);
  };

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

  const statusOf = (index: number) =>
    index < stepIndex ? 'complete' : index === stepIndex ? 'current' : 'pending';

  // A step counts as done once it imported something; until then the way forward is "Omitir".
  const stepDone =
    (step === 'products' && productJob.status === 'completed') ||
    (step === 'customers' && summary.ok > 0) ||
    (step === 'receivables' && summary.ok > 0) ||
    (step === 'payables' && summary.ok > 0);
  const previous = STEPS[stepIndex - 1];
  const next = STEPS[stepIndex + 1];

  return (
    <Stack
      gap={5}
      style={{ maxWidth: 880, margin: '0 auto', padding: 'var(--space-6) var(--space-4)' }}
    >
      <PageHeader
        title="Importa tus datos"
        subtitle="Migración inicial desde Excel. Cada paso es opcional: omite lo que no necesites."
        actions={
          step !== 'done' && (
            <Button variant="ghost" onClick={() => navigate('/dashboard')}>
              Omitir importación
            </Button>
          )
        }
      />

      <Card variant="panel">
        <Stack gap={5}>
          <Timeline variant="stepper" orientation="horizontal" aria-label="Pasos de la importación">
            {STEPS.map((item, index) => (
              <TimelineItem
                key={item.key}
                status={statusOf(index)}
                title={item.title}
                marker={index + 1}
              >
                {item.subtitle}
              </TimelineItem>
            ))}
          </Timeline>

          {step === 'products' && (
            <Stack gap={4}>
              <StepIntro
                title="Productos"
                text="La plantilla incluye listas desplegables para categorías y unidades de medida. Los productos existentes se actualizan por código."
              />
              <TemplateUpload
                onDownload={downloadSafely(downloadProductImportTemplate)}
                file={productFile}
                prompt="Suelta tu Excel de productos o haz clic para buscarlo"
                hint="Formato .xlsx · un producto por fila."
                disabled={productJob.isRunning}
                onFile={(file) => {
                  setProductFile(file);
                  productJob.reset();
                }}
                onClear={() => {
                  setProductFile(null);
                  productJob.reset();
                }}
              />
              {productJob.isRunning && (
                <Progress
                  label={
                    productJob.status === 'uploading'
                      ? 'Subiendo archivo…'
                      : `Importando productos… ${progressPercent(productJob.progress)}%`
                  }
                  value={progressPercent(productJob.progress)}
                />
              )}
              {productJob.status === 'failed' && (
                <Alert tone="danger">{productJob.errorMessage}</Alert>
              )}
              {productJob.result && (
                <ImportSummary
                  lines={[
                    `${productJob.result.created} creados`,
                    `${productJob.result.updated} actualizados`,
                  ]}
                  errors={productJob.result.errors.map((error) => ({
                    row: error.row,
                    ok: false,
                    message: error.message,
                  }))}
                />
              )}
              <Cluster>
                <Button
                  variant="primary"
                  icon="ph ph-upload-simple"
                  disabled={!productFile || productJob.status === 'completed'}
                  loading={productJob.isRunning}
                  onClick={() => productFile && void productJob.start(productFile)}
                >
                  Importar productos
                </Button>
              </Cluster>
            </Stack>
          )}

          {step === 'customers' && (
            <Stack gap={4}>
              <StepIntro
                title="Clientes"
                text="Sube el Excel que ya usas. Solo necesita las columnas RUC/DNI, Nombre, Teléfono y Correo."
              />
              <TemplateUpload
                onDownload={downloadSafely(() =>
                  downloadTemplate(`${TEMPLATE_URL}/customers`, 'plantilla-clientes.xlsx'),
                )}
                file={customerFile}
                prompt="Suelta tu Excel de clientes o haz clic para buscarlo"
                hint="Formato .xlsx · un cliente por fila."
                onFile={setCustomerFile}
                onClear={() => setCustomerFile(null)}
              />
              <Cluster>
                <Button
                  variant="primary"
                  icon="ph ph-upload-simple"
                  disabled={!customerFile}
                  loading={busy}
                  onClick={() => void importCustomers()}
                >
                  Importar clientes
                </Button>
              </Cluster>
            </Stack>
          )}

          {step === 'receivables' && (
            <BalanceStep
              kind="receivables"
              title="Cuentas por cobrar"
              text="Lo que tus clientes te deben. No emite comprobantes SUNAT: es un saldo inicial. Revisa la vista previa; nada se registra hasta confirmar."
              prompt="Suelta tu Excel de cuentas por cobrar o haz clic para buscarlo"
              hint="RUC, nombre, monto y vencimiento."
              file={receivableFile}
              preview={receivablePreview}
              busy={busy}
              error={previewError}
              onFile={(file) => {
                setReceivableFile(file);
                setReceivablePreview(null);
                setPreviewError(null);
              }}
              onClear={() => {
                setReceivableFile(null);
                setReceivablePreview(null);
              }}
              onSubmit={() => void importReceivables()}
            />
          )}

          {step === 'payables' && (
            <BalanceStep
              kind="payables"
              title="Cuentas por pagar"
              text="Lo que debes a tus proveedores. Cada proveedor debe existir en Catálogo → Proveedores. Revisa la vista previa; nada se registra hasta confirmar."
              prompt="Suelta tu Excel de cuentas por pagar o haz clic para buscarlo"
              hint="RUC del proveedor, monto y vencimiento."
              file={payableFile}
              preview={payablePreview}
              busy={busy}
              error={previewError}
              onFile={(file) => {
                setPayableFile(file);
                setPayablePreview(null);
                setPreviewError(null);
              }}
              onClear={() => {
                setPayableFile(null);
                setPayablePreview(null);
              }}
              onSubmit={() => void importPayables()}
            />
          )}

          {step === 'done' && (
            <Stack gap={4}>
              <StepIntro
                title="¡Todo listo!"
                text="Ya puedes vender y cobrar en un solo sistema. Estos son los primeros pasos recomendados."
              />
              <Stack gap={2}>
                <Button
                  variant="secondary"
                  icon="ph ph-buildings"
                  onClick={() => navigate('/config/company')}
                >
                  Revisa razón social y RUC
                </Button>
                <Button
                  variant="secondary"
                  icon="ph ph-hash"
                  onClick={() => navigate('/config/series')}
                >
                  Confirma series SUNAT
                </Button>
                <Button
                  variant="secondary"
                  icon="ph ph-package"
                  onClick={() => navigate('/catalog/products')}
                >
                  Abre el catálogo y verifica precios
                </Button>
                <Button
                  variant="secondary"
                  icon="ph ph-shopping-cart"
                  onClick={() => navigate('/pos')}
                >
                  Haz la primera venta
                </Button>
              </Stack>
            </Stack>
          )}

          {step !== 'done' && results.length > 0 && (
            <ImportSummary
              lines={[`${summary.ok} correctos`, `${summary.failed} con error`]}
              errors={results}
            />
          )}

          <Row justify="between" align="center">
            {previous ? (
              <Button variant="secondary" onClick={() => goTo(previous.key)} disabled={busy}>
                Atrás
              </Button>
            ) : (
              <span />
            )}
            {step === 'done' ? (
              <Button variant="primary" onClick={() => navigate('/dashboard')}>
                Ir al inicio
              </Button>
            ) : (
              next && (
                <Button
                  variant={stepDone ? 'primary' : 'secondary'}
                  onClick={() => goTo(next.key)}
                  disabled={busy || productJob.isRunning}
                >
                  {stepDone ? 'Siguiente' : 'Omitir este paso'}
                </Button>
              )
            )}
          </Row>
        </Stack>
      </Card>
    </Stack>
  );
}

function StepIntro({ title, text }: { title: string; text: string }) {
  return (
    <Stack gap={1}>
      <Text as="p">
        <strong>{title}</strong>
      </Text>
      <Text as="p" tone="muted">
        {text}
      </Text>
    </Stack>
  );
}

/** The same two moves for every kind of data: download the template, then upload the filled file. */
function TemplateUpload({
  onDownload,
  file,
  prompt,
  hint,
  disabled,
  onFile,
  onClear,
}: {
  onDownload: () => void;
  file: File | null;
  prompt: string;
  hint: string;
  disabled?: boolean;
  onFile: (file: File) => void;
  onClear: () => void;
}) {
  return (
    <Stack gap={3}>
      <Row gap={3} align="center" wrap>
        <Badge variant="secondary" pill>
          1
        </Badge>
        <Text>Descarga la plantilla y complétala</Text>
        <Button variant="secondary" size="sm" icon="ph ph-download-simple" onClick={onDownload}>
          Descargar plantilla
        </Button>
      </Row>
      <Row gap={3} align="center">
        <Badge variant="secondary" pill>
          2
        </Badge>
        <Text>Sube el archivo completado</Text>
      </Row>
      <FileDropzone
        accept={XLSX}
        value={file ? { fileName: file.name } : null}
        disabled={disabled}
        labels={{
          prompt,
          hint,
          replace: 'Cambiar archivo',
          remove: 'Quitar',
          open: 'Abrir',
        }}
        onFileSelect={onFile}
        onClear={onClear}
      />
    </Stack>
  );
}

const RESULT_COLUMNS: Array<DataTableColumn<RowResult>> = [
  { id: 'row', header: 'Fila', nowrap: true, render: (row) => (row.row > 0 ? row.row : '—') },
  {
    id: 'status',
    header: 'Estado',
    nowrap: true,
    render: (row) => (
      <Badge variant={row.ok ? 'success' : 'danger'}>{row.ok ? 'OK' : 'Error'}</Badge>
    ),
  },
  { id: 'message', header: 'Detalle', accessor: (row) => row.message },
];

function ImportSummary({ lines, errors }: { lines: string[]; errors: RowResult[] }) {
  return (
    <Stack gap={3}>
      <Row gap={2} wrap>
        {lines.map((line) => (
          <Badge key={line} variant="info">
            {line}
          </Badge>
        ))}
      </Row>
      {errors.length > 0 && (
        <DataTable
          variant="flush"
          columns={RESULT_COLUMNS}
          rows={errors.slice(0, 20)}
          getRowKey={(row) => `${row.row}-${row.message}`}
        />
      )}
    </Stack>
  );
}

function BalanceStep({
  kind,
  title,
  text,
  prompt,
  hint,
  file,
  preview,
  busy,
  error,
  onFile,
  onClear,
  onSubmit,
}: {
  kind: BalanceKind;
  title: string;
  text: string;
  prompt: string;
  hint: string;
  file: File | null;
  preview: Array<Record<string, string>> | null;
  busy: boolean;
  error: string | null;
  onFile: (file: File) => void;
  onClear: () => void;
  onSubmit: () => void;
}) {
  const label = kind === 'receivables' ? 'por cobrar' : 'por pagar';
  return (
    <Stack gap={4}>
      <StepIntro title={title} text={text} />
      <TemplateUpload
        onDownload={downloadSafely(() =>
          downloadTemplate(`${TEMPLATE_URL}/${kind}`, `plantilla-${kind}.xlsx`),
        )}
        file={file}
        prompt={prompt}
        hint={hint}
        onFile={onFile}
        onClear={onClear}
      />
      {preview && <BalancePreview kind={kind} rows={preview} />}
      {error && <Alert tone="danger">{error}</Alert>}
      <Cluster>
        <Button
          variant="primary"
          icon={preview ? 'ph ph-check' : 'ph ph-eye'}
          disabled={!file}
          loading={busy}
          onClick={onSubmit}
        >
          {preview ? `Confirmar ${label}` : `Previsualizar ${label}`}
        </Button>
      </Cluster>
    </Stack>
  );
}

function BalancePreview({
  kind,
  rows,
}: {
  kind: BalanceKind;
  rows: Array<Record<string, string>>;
}) {
  const columns: Array<DataTableColumn<Record<string, string>>> = (
    kind === 'receivables'
      ? ['ruc/dni', 'nombre', 'monto', 'vencimiento', 'numero']
      : ['ruc', 'proveedor', 'monto', 'vencimiento', 'numero']
  ).map((column) => ({
    id: column,
    header: column,
    accessor: (row) => row[column] || '—',
  }));
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
    <Stack
      gap={3}
      aria-label={`Vista previa ${kind === 'receivables' ? 'por cobrar' : 'por pagar'}`}
    >
      <Text>
        <strong>Vista previa · {rows.length} filas</strong>
      </Text>
      <Text tone="muted" as="p">
        Efecto al confirmar:{' '}
        {kind === 'receivables'
          ? 'Dr 121 Cuentas por cobrar · Cr 591 Saldos iniciales'
          : 'Dr 659 Gasto pendiente · Cr 421 Cuentas por pagar'}
        .
      </Text>
      {issues > 0 && (
        <Alert tone="warning">
          {issues} filas tienen datos obligatorios incompletos o un monto inválido. Corrígelas antes
          de confirmar.
        </Alert>
      )}
      <DataTable
        columns={columns}
        rows={visibleRows}
        getRowKey={(row) => visibleRows.indexOf(row)}
      />
      {rows.length > visibleRows.length && (
        <Text tone="muted">
          Mostrando {visibleRows.length} de {rows.length} filas.
        </Text>
      )}
    </Stack>
  );
}
