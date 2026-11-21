import React, { useEffect, useRef, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { useScreenSize } from '@nubitio/admin';
import { AppDialog, Button } from '@nubitio/ui';
import { downloadProductImportTemplate } from '../model/downloadProductImportTemplate';
import { progressPercent, useProductImportJob } from '../model/useProductImportJob';

import './ProductImportDialog.scss';

interface ProductImportDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onImportDone?: () => void;
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export function ProductImportDialog({ isOpen, onClose, onImportDone }: ProductImportDialogProps) {
  const { isXSmall } = useScreenSize();
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const { status, progress, result, errorMessage, isRunning, start, reset } =
    useProductImportJob(onImportDone);

  const prevIsOpenRef = useRef(isOpen);
  useEffect(() => {
    const wasOpen = prevIsOpenRef.current;
    prevIsOpenRef.current = isOpen;
    if (wasOpen && !isOpen) {
      const id = setTimeout(() => {
        setSelectedFile(null);
        reset();
      }, 300);
      return () => clearTimeout(id);
    }
    return undefined;
  }, [isOpen, reset]);

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
      reset();
    },
  });

  // ---------------------------------------------------------------------------
  // Handlers
  // ---------------------------------------------------------------------------

  const handleImport = () => {
    if (selectedFile) void start(selectedFile);
  };

  const handleClose = () => {
    if (status === 'uploading' || status === 'processing') return;
    onClose();
  };

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
          <Button
            variant="secondary"
            size="sm"
            onClick={() => void downloadProductImportTemplate().catch(() => undefined)}
          >
            Descargar plantilla .xlsx
          </Button>
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
          <div
            className={`product-import-result__card${result.errors.length > 0 ? ' product-import-result__card--error' : ''}`}
          >
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
          <Button variant="secondary" onClick={handleClose} disabled={isRunning}>
            Cerrar
          </Button>
          {status === 'idle' && (
            <Button variant="primary" disabled={!selectedFile} onClick={handleImport}>
              Importar
            </Button>
          )}
          {status === 'completed' && (
            <Button
              variant="primary"
              onClick={() => {
                setSelectedFile(null);
                reset();
              }}
            >
              Importar otro
            </Button>
          )}
        </div>
      }
    >
      <div className="product-import-body">{bodyNode}</div>
    </AppDialog>
  );
}
