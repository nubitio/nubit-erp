import { useCallback, useMemo, useState } from 'react';
import { AppToolbar } from '@nubitio/ui';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';

const API = getCoreApiBaseUrl();

function getFilenameFromDisposition(disposition: string | undefined, fallback: string): string {
  if (!disposition) return fallback;

  const utf8Match = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
  if (utf8Match?.[1]) return decodeURIComponent(utf8Match[1].replace(/"/g, ''));

  const match = /filename="?([^";]+)"?/i.exec(disposition);
  return match?.[1] ?? fallback;
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

/** Periodo por defecto: mes en curso en formato AAAAMM. */
function currentPeriod(): string {
  const now = new Date();
  const year = now.getFullYear();
  const month = `${now.getMonth() + 1}`.padStart(2, '0');
  return `${year}${month}`;
}

type Book = 'sales' | 'purchases';

export function PleExportPage() {
  const [period, setPeriod] = useState<string>(currentPeriod());
  const [loading, setLoading] = useState<Book | null>(null);
  const [error, setError] = useState<string | null>(null);

  const periodValid = useMemo(() => /^\d{6}$/.test(period)
    && Number(period.slice(4)) >= 1 && Number(period.slice(4)) <= 12, [period]);

  const download = useCallback(async (book: Book) => {
    if (!periodValid) {
      setError('El periodo debe tener el formato AAAAMM y un mes válido.');
      return;
    }
    setError(null);
    setLoading(book);
    try {
      const url = `${API}v1/ple/${book}?period=${period}`;
      const response = await httpClient.get<Blob>(url, { responseType: 'blob' });
      const fallback = `LE_${book}_${period}.txt`;
      downloadBlob(response.data, getFilenameFromDisposition(response.headers['content-disposition'], fallback));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No se pudo generar el libro electrónico.');
    } finally {
      setLoading(null);
    }
  }, [period, periodValid]);

  return (
    <div className="view-wrapper-scroll">
      <AppToolbar title="Libros Electrónicos (PLE)">
        <div style={{ maxWidth: 560, padding: '1rem', display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <p style={{ color: 'var(--secondary-text-color, #666)', margin: 0 }}>
            Genera los archivos de texto del Programa de Libros Electrónicos para
            cargarlos en el aplicativo PLE de SUNAT.
          </p>

          <label style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            <span>Periodo (AAAAMM)</span>
            <input
              type="text"
              inputMode="numeric"
              maxLength={6}
              value={period}
              onChange={(e) => setPeriod(e.target.value.replace(/\D/g, '').slice(0, 6))}
              placeholder="202606"
              style={{ padding: '0.5rem', maxWidth: 160 }}
            />
          </label>

          {error && <div className="dashboard-error" style={{ color: 'var(--danger-color, #c0392b)' }}>{error}</div>}

          <div style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
            <button
              type="button"
              className="btn btn-primary"
              disabled={!periodValid || loading !== null}
              onClick={() => download('sales')}
            >
              {loading === 'sales' ? 'Generando…' : 'Registro de Ventas (14.1)'}
            </button>
            <button
              type="button"
              className="btn btn-primary"
              disabled={!periodValid || loading !== null}
              onClick={() => download('purchases')}
            >
              {loading === 'purchases' ? 'Generando…' : 'Registro de Compras (8.1)'}
            </button>
          </div>
        </div>
      </AppToolbar>
    </div>
  );
}
