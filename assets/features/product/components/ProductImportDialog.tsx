import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { useScreenSize } from '@nubit/admin';
import { MercureManager, useMercureHub } from '@nubit/core';
import { AppDialog } from '@nubit/ui';
import { businessHttpClient } from '@shared/api/network';

import './ProductImportDialog.scss';

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

  const jobIdRef = useRef<string | null>(null);

  const prevIsOpenRef = useRef(isOpen);
  useEffect(() => {
    const wasOpen = prevIsOpenRef.current;
    prevIsOpenRef.current = isOpen;
    if (wasOpen && !isOpen) {
      const id = setTimeout(() => {
        setStatus('idle');
        setSelectedFile(null);
        setProgress(null);
        setResult(null);
        setErrorMessage(null);
        jobIdRef.current = null;
      }, 300);
      return () => clearTimeout(id);
    }
    return undefined;
  }, [isOpen]);

  // ---------------------------------------------------------------------------
  // Mercure
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
  // Dropzone
  // ---------------------------------------------------------------------------

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    accept: {
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx'],
    },
    multiple: false,
    disabled: status !== 'idle',
    onDrop: (acceptedFiles) => {
      const file = acceptedFiles[0] ?? null;
      setSelectedFile(file);
      setStatus('idle');
      setResult(null);
      setErrorMessage(null);
    },
  });

  // ---------------------------------------------------------------------------
  // Handlers
  // ---------------------------------------------------------------------------

  const handleImport = async () => {
    if (!selectedFile) return;
    setStatus('uploading');
    setProgress(null);
    setResult(null);
    setErrorMessage(null);

    const formData = new FormData();
    formData.append('file', selectedFile);

    try {
      const response = await businessHttpClient.post<{ jobId: string; status: string; topic: string }>(
        '/api/v1/products/import',
        formData,
      );
      jobIdRef.current = response.data.jobId;
      setStatus('processing');
      subscribeMercure(response.data.topic);
    } catch (err) {
      setErrorMessage(err instanceof Error ? err.message : 'Error al subir el archivo');
      setStatus('failed');
    }
  };

  const handleClose = () => {
    if (status === 'uploading' || status === 'processing') return;
    onClose();
  };

  const isRunning = status === 'uploading' || status === 'processing';
  const pct = progressPercent(progress);

  // ---------------------------------------------------------------------------
  // Body sections
  // ---------------------------------------------------------------------------

  const renderIdleBody = () => (
    <div className="product-import-steps">
      {/* Step 1 */}
      <div className="product-import-step">
        <span className="product-import-step__badge">1</span>
        <div className="product-import-step__content">
          <p className="product-import-step__title">Descarga la plantilla</p>
          <p className="product-import-step__desc">
            Incluye columnas con listas desplegables para categorías y unidades de medida.
          </p>
          <button
            type="button"
            className="app-button app-button--secondary app-button--sm"
            onClick={() => window.open('/api/v1/products/import/template', '_blank')}
          >
            Descargar plantilla .xlsx
          </button>
        </div>
      </div>

      {/* Step 2 */}
      <div className="product-import-step">
        <span className="product-import-step__badge">2</span>
        <div className="product-import-step__content">
          <p className="product-import-step__title">Completa y sube el archivo</p>
          <p className="product-import-step__desc">
            Rellena las filas con tus productos y sube el archivo .xlsx.
          </p>
          <div
            {...getRootProps()}
            className={`product-import-dropzone${isDragActive ? ' product-import-dropzone--active' : ''}${selectedFile ? ' product-import-dropzone--has-file' : ''}`}
          >
            <input {...getInputProps()} />
            {selectedFile ? (
              <span className="product-import-dropzone__filename">
                <i className="ph ph-file-xls" aria-hidden="true" /> {selectedFile.name}
              </span>
            ) : isDragActive ? (
              <span>Suelta el archivo aquí…</span>
            ) : (
              <span>
                <i className="ph ph-upload-simple" aria-hidden="true" /> Seleccionar archivo .xlsx
              </span>
            )}
          </div>
        </div>
      </div>
    </div>
  );

  const renderProcessingBody = () => (
    <div className="product-import-progress">
      <p className="product-import-progress__label">
        {status === 'uploading' ? 'Subiendo archivo…' : `Importando productos… ${pct}%`}
      </p>
      <progress
        className="product-import-progress__bar"
        value={pct}
        max={100}
        aria-label="Progreso de importación"
      />
      {progress && (
        <p className="product-import-progress__detail">
          {progress.current} / {progress.total} filas procesadas
        </p>
      )}
    </div>
  );

  const renderCompletedBody = () => {
    if (!result) return null;
    return (
      <div className="product-import-result">
        <div className="product-import-result__cards">
          <div className="product-import-result__card product-import-result__card--created">
            <span className="product-import-result__value">{result.created}</span>
            <span className="product-import-result__label">Creados</span>
          </div>
          <div className="product-import-result__card product-import-result__card--updated">
            <span className="product-import-result__value">{result.updated}</span>
            <span className="product-import-result__label">Actualizados</span>
          </div>
          <div className={`product-import-result__card${result.errors.length > 0 ? ' product-import-result__card--error' : ''}`}>
            <span className="product-import-result__value">{result.errors.length}</span>
            <span className="product-import-result__label">Errores</span>
          </div>
        </div>
        {result.errors.length > 0 && (
          <div className="product-import-result__errors">
            <p className="product-import-result__errors-title">Filas con errores:</p>
            {result.errors.map((e, i) => (
              <div key={i} className="product-import-result__error-row">
                Fila {e.row}: {e.message}
              </div>
            ))}
          </div>
        )}
      </div>
    );
  };

  const renderFailedBody = () => (
    <div className="product-import-failed">
      <i className="ph ph-warning product-import-failed__icon" aria-hidden="true" />
      <p className="product-import-failed__message">
        <strong>Error:</strong> {errorMessage}
      </p>
    </div>
  );

  const bodyNode = isRunning
    ? renderProcessingBody()
    : status === 'completed'
      ? renderCompletedBody()
      : status === 'failed'
        ? renderFailedBody()
        : renderIdleBody();

  return (
    <AppDialog
      title="Importar productos"
      open={isOpen}
      fullScreen={isXSmall}
      width={520}
      height="auto"
      showCloseButton={!isRunning}
      closeOnOutsideClick={false}
      onClose={handleClose}
      footer={
        <div className="product-import-footer">
          <button
            type="button"
            className="app-button app-button--secondary"
            onClick={handleClose}
            disabled={isRunning}
          >
            Cerrar
          </button>
          {status === 'idle' && (
            <button
              type="button"
              className="app-button app-button--primary"
              disabled={!selectedFile}
              onClick={() => void handleImport()}
            >
              Importar
            </button>
          )}
          {status === 'completed' && (
            <button
              type="button"
              className="app-button app-button--primary"
              onClick={() => {
                setStatus('idle');
                setSelectedFile(null);
                setResult(null);
              }}
            >
              Importar otro
            </button>
          )}
        </div>
      }
    >
      <div className="product-import-body">{bodyNode}</div>
    </AppDialog>
  );
}
