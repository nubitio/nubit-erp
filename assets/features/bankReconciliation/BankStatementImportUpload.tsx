import { useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button, Card, FormField } from '@nubitio/ui';
import { EntityDropdown } from '@nubitio/crud';
import { MercureManager, useMercureHub, getCoreApiBaseUrl } from '@nubitio/core';
import toast from '@shared/lib/SharedUtils';
import bankStatementImportService from './services/bankStatementImportService';
import { BANK_PROFILE_OPTIONS } from './model/constants';

const API = getCoreApiBaseUrl();

type UploadStatus = 'idle' | 'uploading' | 'processing' | 'completed' | 'failed';

interface Progress {
  current: number;
  total: number;
}

interface ImportError {
  row: number;
  message: string;
}

interface ImportResult {
  totalRows: number;
  importedRows: number;
  errorRows: number;
  errors: ImportError[];
}

interface MercureEvent {
  importId: string;
  status: 'processing' | 'completed' | 'failed';
  progress?: Progress;
  result?: ImportResult;
  error?: string;
}

function progressPercent(p: Progress | null): number {
  if (!p || p.total === 0) return 0;
  return Math.round((p.current / p.total) * 100);
}

export const BankStatementImportUpload = () => {
  const navigate = useNavigate();
  const hubUrl = useMercureHub();
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [bankAccountId, setBankAccountId] = useState<string | null>(null);
  const [bankProfile, setBankProfile] = useState<string>('GENERIC');
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [status, setStatus] = useState<UploadStatus>('idle');
  const [progress, setProgress] = useState<Progress | null>(null);
  const [result, setResult] = useState<ImportResult | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [importId, setImportId] = useState<string | null>(null);

  const subscribeMercure = (topic: string, newImportId: string) => {
    if (!hubUrl) return;

    const handler = (data: unknown) => {
      const event = data as MercureEvent;
      if (event.status === 'processing' && event.progress) {
        setProgress(event.progress);
      } else if (event.status === 'completed' && event.result) {
        setResult(event.result);
        setStatus('completed');
        MercureManager.unsubscribe(topic, handler);
        bankStatementImportService()
          .autoMatch(newImportId)
          .catch(() => {
            // Auto-match failures don't block reviewing the import manually.
          });
      } else if (event.status === 'failed') {
        setErrorMessage(event.error ?? 'Error desconocido');
        setStatus('failed');
        MercureManager.unsubscribe(topic, handler);
      }
    };

    MercureManager.subscribe(topic, handler);
  };

  const handleImport = () => {
    if (!selectedFile || !bankAccountId) {
      toast('Selecciona la cuenta bancaria y el archivo a importar', 'error');
      return;
    }

    setStatus('uploading');
    setProgress(null);
    setResult(null);
    setErrorMessage(null);

    bankStatementImportService()
      .upload(selectedFile, bankAccountId, bankProfile)
      .then((response) => {
        setImportId(response.data.importId);
        setStatus('processing');
        subscribeMercure(response.data.topic, response.data.importId);
      })
      .catch((err) => {
        setErrorMessage(err instanceof Error ? err.message : 'Error al subir el archivo');
        setStatus('failed');
      });
  };

  const isRunning = status === 'uploading' || status === 'processing';
  const pct = progressPercent(progress);

  return (
    <Card title="Importar extracto bancario">
      <div style={{ maxWidth: 480 }}>
        {status === 'idle' && (
          <>
            <div className="mb-3">
              <FormField label="Cuenta bancaria" required htmlFor="bank-statement-import-account">
                <EntityDropdown
                  url={API + 'bank-accounts'}
                  value={bankAccountId}
                  onChange={setBankAccountId}
                  placeholder="Selecciona una cuenta bancaria"
                  searchExpr={['alias']}
                />
              </FormField>
            </div>

            <div className="mb-3">
              <FormField
                label="Banco de origen del archivo"
                htmlFor="bank-statement-import-profile"
              >
                <select
                  id="bank-statement-import-profile"
                  className="form-control"
                  value={bankProfile}
                  onChange={(e) => setBankProfile(e.target.value)}
                >
                  {BANK_PROFILE_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </FormField>
            </div>

            <div className="mb-3">
              <FormField
                label="Archivo (.xlsx o .csv)"
                required
                htmlFor="bank-statement-import-file"
              >
                <input
                  ref={fileInputRef}
                  id="bank-statement-import-file"
                  type="file"
                  accept=".xlsx,.csv"
                  className="form-control"
                  onChange={(e) => setSelectedFile(e.target.files?.[0] ?? null)}
                />
              </FormField>
            </div>

            <Button
              variant="primary"
              fullWidth
              disabled={!selectedFile || !bankAccountId}
              onClick={handleImport}
            >
              Importar
            </Button>
          </>
        )}

        {isRunning && (
          <div>
            <p>
              {status === 'uploading' ? 'Subiendo archivo…' : `Importando movimientos… ${pct}%`}
            </p>
            <progress
              value={pct}
              max={100}
              aria-label="Progreso de importación"
              style={{ width: '100%' }}
            />
            {progress && (
              <p>
                {progress.current} / {progress.total} filas procesadas
              </p>
            )}
          </div>
        )}

        {status === 'completed' && result && (
          <div>
            <p>
              Importadas {result.importedRows} de {result.totalRows} filas
              {result.errorRows > 0 ? ` (${result.errorRows} con error)` : ''}.
            </p>
            {result.errors.length > 0 && (
              <ul>
                {result.errors.map((e, i) => (
                  <li key={i}>
                    Fila {e.row}: {e.message}
                  </li>
                ))}
              </ul>
            )}
            <Button
              variant="success"
              fullWidth
              onClick={() => importId && navigate(`/bank-reconciliation/${importId}`)}
            >
              Ir a conciliar
            </Button>
          </div>
        )}

        {status === 'failed' && (
          <div>
            <p>
              <strong>Error:</strong> {errorMessage}
            </p>
            <Button variant="secondary" fullWidth onClick={() => setStatus('idle')}>
              Reintentar
            </Button>
          </div>
        )}
      </div>
    </Card>
  );
};
