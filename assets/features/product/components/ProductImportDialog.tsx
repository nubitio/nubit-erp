import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from 'devextreme-react';
import { Popup, ToolbarItem } from 'devextreme-react/popup';
import { useScreenSize } from '@utils/media-query';
import MercureManager from '@core/mercure/MercureManager';
import { useMercureHub } from '@core/mercure/useMercureHub';
import { businessHttpClient } from '@shared/api/network';
import { firstValueFrom } from 'rxjs';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type ImportStatus = 'idle' | 'uploading' | 'processing' | 'completed' | 'failed';

interface Progress {
  current: number;
  total: number;
}

interface ImportError {
  row: number;
  message: string;
}

interface ImportResult {
  created: number;
  updated: number;
  errors: ImportError[];
}

interface MercureEvent {
  jobId: string;
  status: 'processing' | 'completed' | 'failed';
  progress?: Progress;
  result?: ImportResult;
  error?: string;
}

interface ProductImportDialogProps {
  isOpen: boolean;
  onClose: () => void;
  /** Called when import completes successfully (with or without row errors). */
  onImportDone?: () => void;
}

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function progressPercent(p: Progress | null): number {
  if (!p || p.total === 0) return 0;
  return Math.round((p.current / p.total) * 100);
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export function ProductImportDialog({ isOpen, onClose, onImportDone }: ProductImportDialogProps) {
  const { isXSmall } = useScreenSize();
  const hubUrl = useMercureHub();

  const [status, setStatus] = useState<ImportStatus>('idle');
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [progress, setProgress] = useState<Progress | null>(null);
  const [result, setResult] = useState<ImportResult | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const fileInputRef = useRef<HTMLInputElement>(null);
  const jobIdRef = useRef<string | null>(null);

  // Reset state when dialog closes (we check isOpen going false)
  const prevIsOpenRef = useRef(isOpen);
  useEffect(() => {
    const wasOpen = prevIsOpenRef.current;
    prevIsOpenRef.current = isOpen;
    if (wasOpen && !isOpen) {
      // Schedule reset outside of the current render
      const id = setTimeout(() => {
        setStatus('idle');
        setSelectedFile(null);
        setProgress(null);
        setResult(null);
        setErrorMessage(null);
        jobIdRef.current = null;
      }, 300); // matches Popup close animation
      return () => clearTimeout(id);
    }
    return undefined;
  }, [isOpen]);

  // ---------------------------------------------------------------------------
  // Mercure subscription for this job
  // ---------------------------------------------------------------------------

  const subscribeMercure = useCallback(
    (topic: string) => {
      if (!hubUrl) return;

      const handler = (data: unknown) => {
        const event = data as MercureEvent;

        if (event.status === 'processing' && event.progress) {
          setProgress(event.progress);
        } else if (event.status === 'completed' && event.result) {
          setResult(event.result);
          setStatus('completed');
          MercureManager.unsubscribe(topic, handler);
          onImportDone?.();
        } else if (event.status === 'failed') {
          setErrorMessage(event.error ?? 'Error desconocido');
          setStatus('failed');
          MercureManager.unsubscribe(topic, handler);
        }
      };

      MercureManager.subscribe(topic, handler);
    },
    [hubUrl, onImportDone],
  );

  // ---------------------------------------------------------------------------
  // Handlers
  // ---------------------------------------------------------------------------

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] ?? null;
    setSelectedFile(file);
    setStatus('idle');
    setResult(null);
    setErrorMessage(null);
  };

  const handleImport = async () => {
    if (!selectedFile) return;

    setStatus('uploading');
    setProgress(null);
    setResult(null);
    setErrorMessage(null);

    const formData = new FormData();
    formData.append('file', selectedFile);

    try {
      const response = await firstValueFrom(
        businessHttpClient.post<{ jobId: string; status: string; topic: string }>(
          '/api/v1/products/import',
          formData,
        ),
      );
      const { jobId, topic } = response.data;
      jobIdRef.current = jobId;

      setStatus('processing');
      subscribeMercure(topic);
    } catch (err) {
      setErrorMessage(err instanceof Error ? err.message : 'Error al subir el archivo');
      setStatus('failed');
    }
  };

  const handleDownloadTemplate = () => {
    window.open('/api/v1/products/import/template', '_blank');
  };

  const handleClose = () => {
    if (status === 'uploading' || status === 'processing') return; // block close while running
    onClose();
  };

  // ---------------------------------------------------------------------------
  // Render helpers
  // ---------------------------------------------------------------------------

  const isRunning = status === 'uploading' || status === 'processing';
  const pct = progressPercent(progress);

  const renderIdleBody = () => (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
      {/* Step 1 */}
      <div style={stepCardStyle}>
        <StepBadge n={1} />
        <div style={{ flex: 1 }}>
          <p style={stepTitleStyle}>Descarga la plantilla</p>
          <p style={stepDescStyle}>
            Incluye columnas con listas desplegables para categorías y unidades de medida.
          </p>
          <DownloadButton
            label="Descargar plantilla .xlsx"
            icon="📋"
            onClick={handleDownloadTemplate}
          />
        </div>
      </div>

      {/* Step 2 */}
      <div style={stepCardStyle}>
        <StepBadge n={2} />
        <div style={{ flex: 1 }}>
          <p style={stepTitleStyle}>Completa y sube el archivo</p>
          <p style={stepDescStyle}>
            Rellena las filas con tus productos y sube el archivo .xlsx.
          </p>
          <label
            htmlFor="product-import-file"
            style={{
              display: 'inline-block',
              cursor: 'pointer',
              padding: '8px 16px',
              border: '1px dashed #94a3b8',
              borderRadius: 6,
              color: '#475569',
              background: selectedFile ? '#f0fdf4' : '#f8fafc',
              fontSize: 13,
              marginTop: 4,
            }}
          >
            {selectedFile ? `📄 ${selectedFile.name}` : '📁 Seleccionar archivo .xlsx'}
          </label>
          <input
            id="product-import-file"
            ref={fileInputRef}
            type="file"
            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
            style={{ display: 'none' }}
            onChange={handleFileChange}
          />
        </div>
      </div>
    </div>
  );

  const renderProcessingBody = () => (
    <div style={{ textAlign: 'center', padding: '24px 0' }}>
      <div style={{ fontSize: 14, color: '#475569', marginBottom: 12 }}>
        {status === 'uploading' ? 'Subiendo archivo…' : `Importando productos… ${pct}%`}
      </div>
      <ProgressBar pct={pct} />
      {progress && (
        <div style={{ fontSize: 12, color: '#94a3b8', marginTop: 8 }}>
          {progress.current} / {progress.total} filas procesadas
        </div>
      )}
    </div>
  );

  const renderCompletedBody = () => {
    if (!result) return null;
    return (
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        <div style={{ display: 'flex', gap: 12 }}>
          <ResultCard label="Creados" value={result.created} color="#16a34a" />
          <ResultCard label="Actualizados" value={result.updated} color="#2563eb" />
          <ResultCard label="Errores" value={result.errors.length} color={result.errors.length > 0 ? '#dc2626' : '#94a3b8'} />
        </div>
        {result.errors.length > 0 && (
          <div
            style={{
              maxHeight: 200,
              overflowY: 'auto',
              border: '1px solid #fecaca',
              borderRadius: 6,
              background: '#fff5f5',
              padding: '8px 12px',
            }}
          >
            <p style={{ fontWeight: 600, color: '#dc2626', marginBottom: 6, fontSize: 13 }}>
              Filas con errores:
            </p>
            {result.errors.map((e, i) => (
              <div key={i} style={{ fontSize: 12, color: '#b91c1c', marginBottom: 4 }}>
                Fila {e.row}: {e.message}
              </div>
            ))}
          </div>
        )}
      </div>
    );
  };

  const renderFailedBody = () => (
    <div
      style={{
        padding: 16,
        background: '#fff5f5',
        border: '1px solid #fecaca',
        borderRadius: 6,
        color: '#b91c1c',
        fontSize: 13,
      }}
    >
      <strong>Error:</strong> {errorMessage}
    </div>
  );

  const bodyNode =
    status === 'idle' || status === 'uploading' || status === 'processing'
      ? isRunning
        ? renderProcessingBody()
        : renderIdleBody()
      : status === 'completed'
        ? renderCompletedBody()
        : renderFailedBody();

  return (
    <Popup
      visible={isOpen}
      title="Importar productos"
      fullScreen={isXSmall}
      width={520}
      height="auto"
      maxHeight="90vh"
      showCloseButton={!isRunning}
      dragEnabled={false}
      hideOnOutsideClick={false}
      onHiding={handleClose}
      wrapperAttr={{ class: 'form-popup' }}
    >
      <ToolbarItem toolbar="bottom" location="after">
        <div className="form-popup-buttons-container">
          <Button
            text="Cerrar"
            stylingMode="outlined"
            onClick={handleClose}
            disabled={isRunning}
          />
          {(status === 'idle') && (
            <Button
              text="Importar"
              stylingMode="outlined"
              type="default"
              disabled={!selectedFile}
              onClick={() => void handleImport()}
            />
          )}
          {status === 'completed' && (
            <Button
              text="Importar otro"
              stylingMode="outlined"
              type="default"
              onClick={() => {
                setStatus('idle');
                setSelectedFile(null);
                setResult(null);
                if (fileInputRef.current) fileInputRef.current.value = '';
              }}
            />
          )}
        </div>
      </ToolbarItem>

      <div style={{ padding: '8px 4px' }}>{bodyNode}</div>
    </Popup>
  );
}

// ---------------------------------------------------------------------------
// Small sub-components
// ---------------------------------------------------------------------------

function StepBadge({ n }: { n: number }) {
  return (
    <div
      style={{
        width: 28,
        height: 28,
        borderRadius: '50%',
        background: '#1e40af',
        color: '#fff',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontWeight: 700,
        fontSize: 13,
        flexShrink: 0,
        marginTop: 2,
      }}
    >
      {n}
    </div>
  );
}

function ProgressBar({ pct }: { pct: number }) {
  return (
    <div
      style={{
        width: '100%',
        height: 10,
        borderRadius: 5,
        background: '#e2e8f0',
        overflow: 'hidden',
      }}
    >
      <div
        style={{
          height: '100%',
          width: `${pct}%`,
          background: 'linear-gradient(90deg, #1d4ed8, #3b82f6)',
          borderRadius: 5,
          transition: 'width 0.3s ease',
        }}
      />
    </div>
  );
}

function ResultCard({ label, value, color }: { label: string; value: number; color: string }) {
  return (
    <div
      style={{
        flex: 1,
        textAlign: 'center',
        padding: '12px 8px',
        border: `1px solid ${color}22`,
        borderRadius: 8,
        background: `${color}0a`,
      }}
    >
      <div style={{ fontSize: 28, fontWeight: 700, color }}>{value}</div>
      <div style={{ fontSize: 12, color: '#64748b', marginTop: 2 }}>{label}</div>
    </div>
  );
}

function DownloadButton({
  label,
  icon,
  primary = false,
  onClick,
}: {
  label: string;
  icon: string;
  primary?: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 8,
        width: '100%',
        padding: '9px 14px',
        border: `1px solid ${primary ? '#1d4ed8' : '#cbd5e1'}`,
        borderRadius: 6,
        background: primary ? '#eff6ff' : '#fff',
        color: primary ? '#1d4ed8' : '#374151',
        fontSize: 13,
        fontWeight: 500,
        cursor: 'pointer',
        textAlign: 'left',
      }}
    >
      <span style={{ fontSize: 15, lineHeight: 1 }}>{icon}</span>
      {label}
    </button>
  );
}


const stepCardStyle: React.CSSProperties = {
  display: 'flex',
  gap: 12,
  padding: 16,
  background: '#f8fafc',
  borderRadius: 8,
  border: '1px solid #e2e8f0',
};

const stepTitleStyle: React.CSSProperties = {
  fontWeight: 600,
  fontSize: 14,
  color: '#1e293b',
  margin: '0 0 4px',
};

const stepDescStyle: React.CSSProperties = {
  fontSize: 12,
  color: '#64748b',
  margin: '0 0 10px',
};
