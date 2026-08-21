import { useCallback, useEffect, useMemo, useState } from 'react';
import { AppDropdown, Button } from '@nubitio/ui';
import { ErpModuleBanner } from '@features/shared/ErpModuleBanner';
import {
  currentPeriod,
  formatFileSize,
  formatMoney,
  isJournalBook,
  isValidPeriod,
  periodLabel,
  pleService,
  type PleBookPreview,
  type PleBookValidation,
  type PleDownloadBook,
  type PlePreviewResponse,
  type PleValidationResponse,
} from './services/pleService';
import './PleExportPage.scss';

type ActiveBook = PleDownloadBook;

const MONTH_OPTIONS = Array.from({ length: 12 }, (_, index) => {
  const month = index + 1;
  const value = `${month}`.padStart(2, '0');
  const label = new Date(2024, index, 1).toLocaleDateString('es-PE', { month: 'long' });
  return { value, label: label.charAt(0).toUpperCase() + label.slice(1) };
});

function buildYearOptions(anchorYear: number) {
  return [anchorYear - 1, anchorYear, anchorYear + 1].map((year) => ({
    value: String(year),
    label: String(year),
  }));
}

function formatGeneratedAt(iso: string | undefined): string {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleDateString('es-PE', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function cardMeta(book: PleBookPreview): string {
  if (book.status === 'unavailable') return 'Sin datos aún';
  if (book.recordCount === 0) return 'Sin registros en el periodo';
  return `${book.recordCount} registro${book.recordCount === 1 ? '' : 's'} · ${formatFileSize(book.sizeBytes)}`;
}

function resolveBookStatus(
  book: PleBookPreview,
  bookKey: ActiveBook,
  validation: PleValidationResponse | null,
): { label: string; tone: 'ready' | 'empty' | 'unavailable' | 'error' } {
  if (book.status === 'unavailable') {
    return { label: 'Sin datos aún', tone: 'unavailable' };
  }
  if (book.recordCount === 0) {
    return { label: 'Sin registros', tone: 'empty' };
  }

  const bookValidation = validation?.books[bookKey];
  if (bookValidation) {
    if (!bookValidation.valid) {
      return {
        label: `Revisar ${bookValidation.issueCount} error${bookValidation.issueCount === 1 ? '' : 'es'}`,
        tone: 'error',
      };
    }
    return { label: 'Listo para enviar', tone: 'ready' };
  }

  return { label: book.statusLabel, tone: 'ready' };
}

function mergeValidation(
  previous: PleValidationResponse | null,
  incoming: PleValidationResponse,
  scope: 'all' | ActiveBook,
): PleValidationResponse {
  if (scope === 'all') {
    return incoming;
  }

  const mergedBooks = {
    ...(previous?.books ?? {}),
    ...incoming.books,
  };
  const valid = Object.values(mergedBooks).every((book) => book?.valid ?? true);

  return {
    ...incoming,
    valid,
    books: mergedBooks,
  };
}

function PleIssuesPanel({ validation }: { validation: PleBookValidation }) {
  if (validation.valid || validation.issues.length === 0) return null;

  return (
    <div className="ple-issues" role="alert" data-testid="ple-issues">
      <p className="ple-issues__title">
        Se encontraron {validation.issueCount} problema{validation.issueCount === 1 ? '' : 's'}
      </p>
      <ul className="ple-issues__list" data-testid="ple-issues-list">
        {validation.issues.map((issue) => (
          <li key={`${issue.code}-${issue.row ?? 'global'}-${issue.message}`}>
            {issue.row ? `Fila ${issue.row}: ` : ''}
            {issue.message}
          </li>
        ))}
      </ul>
    </div>
  );
}

function voucherLabel(row: PleBookPreview['rows'][number]): string {
  const parts = [row.documentType, row.series, row.number].filter((part) => part && part.trim() !== '');
  return parts.length > 0 ? parts.join(' ') : '—';
}

function PleJournalTable({ book }: { book: PleBookPreview }) {
  if (book.rows.length === 0) {
    return (
      <div className="ple-empty">
        No hay asientos para este libro en el periodo seleccionado.
      </div>
    );
  }

  return (
    <div className="ple-table-wrap">
      <table className="ple-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Fecha</th>
            <th>CUO</th>
            <th>Cuenta</th>
            <th>Glosa</th>
            <th>Comprobante</th>
            <th>Debe</th>
            <th>Haber</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          {book.rows.map((row) => (
            <tr key={`${row.cuo ?? ''}-${row.correlative ?? ''}-${row.accountCode ?? ''}-${row.index}`}>
              <td>{row.index}</td>
              <td>{row.issueDate}</td>
              <td>{row.cuo || '—'}</td>
              <td title={row.accountName}>
                <span className="ple-table__account">{row.accountCode}</span>
                {row.accountName ? <span className="ple-table__account-name">{row.accountName}</span> : null}
              </td>
              <td className="ple-table__name" title={row.glosa ?? row.counterpartyName}>
                {row.glosa || row.counterpartyName || '—'}
              </td>
              <td>{voucherLabel(row)}</td>
              <td>{formatMoney(row.debit ?? 0)}</td>
              <td>{formatMoney(row.credit ?? 0)}</td>
              <td>
                <span className="ple-badge ple-badge--active">{row.stateLabel}</span>
              </td>
            </tr>
          ))}
          <tr className="ple-table__totals">
            <td colSpan={6}>TOTALES</td>
            <td>{formatMoney(book.totals.debit ?? 0)}</td>
            <td>{formatMoney(book.totals.credit ?? 0)}</td>
            <td />
          </tr>
        </tbody>
      </table>
    </div>
  );
}

function PleLedgerTable({ book }: { book: PleBookPreview }) {
  if (book.rows.length === 0) {
    return (
      <div className="ple-empty">
        No hay registros para este libro en el periodo seleccionado.
      </div>
    );
  }

  return (
    <div className="ple-table-wrap">
      <table className="ple-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Fecha emisión</th>
            <th>Tipo</th>
            <th>Serie</th>
            <th>Número</th>
            <th>RUC/DNI</th>
            <th>Razón social</th>
            <th>Base imponible</th>
            <th>IGV</th>
            <th>Total</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          {book.rows.map((row) => (
            <tr key={`${row.documentType}-${row.series}-${row.number}-${row.index}`}>
              <td>{row.index}</td>
              <td>{row.issueDate}</td>
              <td>{row.documentType}</td>
              <td>{row.series}</td>
              <td>{row.number}</td>
              <td>{row.counterpartyDoc || '—'}</td>
              <td className="ple-table__name" title={row.counterpartyName}>
                {row.counterpartyName}
              </td>
              <td className={row.taxable < 0 ? 'is-negative' : undefined}>{formatMoney(row.taxable)}</td>
              <td className={row.igv < 0 ? 'is-negative' : undefined}>{formatMoney(row.igv)}</td>
              <td className={row.total < 0 ? 'is-negative' : undefined}>{formatMoney(row.total)}</td>
              <td>
                <span className={`ple-badge ${row.state === '2' ? 'ple-badge--void' : 'ple-badge--active'}`}>
                  {row.stateLabel}
                </span>
              </td>
            </tr>
          ))}
          <tr className="ple-table__totals">
            <td colSpan={7}>TOTALES</td>
            <td>{formatMoney(book.totals.taxable)}</td>
            <td>{formatMoney(book.totals.igv)}</td>
            <td>{formatMoney(book.totals.total)}</td>
            <td />
          </tr>
        </tbody>
      </table>
    </div>
  );
}

export function PleExportPage() {
  const [period, setPeriod] = useState(currentPeriod);
  const [activeBook, setActiveBook] = useState<ActiveBook>('sales');
  const [preview, setPreview] = useState<PlePreviewResponse | null>(null);
  const [validation, setValidation] = useState<PleValidationResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [validating, setValidating] = useState<'all' | ActiveBook | null>(null);
  const [downloading, setDownloading] = useState<PleDownloadBook | null>(null);
  const [downloadingZip, setDownloadingZip] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const periodValid = useMemo(() => isValidPeriod(period), [period]);
  const year = period.slice(0, 4);
  const month = period.slice(4, 6);
  const yearOptions = useMemo(() => buildYearOptions(Number(year) || new Date().getFullYear()), [year]);

  const loadPreview = useCallback(async () => {
    if (!periodValid) {
      setError('Selecciona un periodo válido.');
      setPreview(null);
      setValidation(null);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    setValidation(null);
    try {
      const data = await pleService.preview(period);
      setPreview(data);
    } catch (err) {
      setPreview(null);
      setError(err instanceof Error ? err.message : 'No se pudo cargar la vista previa del PLE.');
    } finally {
      setLoading(false);
    }
  }, [period, periodValid]);

  useEffect(() => {
    void loadPreview();
  }, [loadPreview]);

  const selectedBook = preview?.books[activeBook];
  const salesBook = preview?.books.sales;
  const purchasesBook = preview?.books.purchases;
  const diaryBook = preview?.books.diary;
  const ledgerBook = preview?.books.ledger;
  const activeValidation = validation?.books[activeBook];
  const journalSelected = isJournalBook(activeBook);

  const updatePeriod = (nextYear: string, nextMonth: string) => {
    setPeriod(`${nextYear}${nextMonth}`);
  };

  const runValidation = async (scope: 'all' | ActiveBook) => {
    if (!periodValid) return;

    setValidating(scope === 'all' ? 'all' : scope);
    setError(null);
    try {
      const result = await pleService.validate(period, scope === 'all' ? undefined : scope);
      setValidation((previous) => mergeValidation(previous, result, scope));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No se pudo validar el PLE.');
    } finally {
      setValidating(null);
    }
  };

  const downloadBook = async (book: PleDownloadBook) => {
    if (!periodValid || !preview) return;
    const target = preview.books[book];
    if (!target || target.status === 'unavailable' || target.recordCount === 0) return;

    setDownloading(book);
    setError(null);
    try {
      await pleService.download(book, period);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No se pudo descargar el libro electrónico.');
    } finally {
      setDownloading(null);
    }
  };

  const downloadZip = async () => {
    if (!periodValid) return;

    setDownloadingZip(true);
    setError(null);
    try {
      await pleService.downloadZip(period);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No se pudo descargar el ZIP del PLE.');
    } finally {
      setDownloadingZip(false);
    }
  };

  const bookCards = preview
    ? [
        { key: 'sales' as const, book: preview.books.sales },
        { key: 'purchases' as const, book: preview.books.purchases },
        { key: 'diary' as const, book: preview.books.diary },
        { key: 'ledger' as const, book: preview.books.ledger },
      ]
    : [];

  const selectedStatus = selectedBook
    ? resolveBookStatus(selectedBook, activeBook, validation)
    : null;

  const busy = loading || validating !== null || downloading !== null || downloadingZip;

  return (
    <div className="view-wrapper-scroll">
      <div className="ple-page" data-testid="ple-page">
        <header className="ple-page__header">
          <div className="ple-page__intro">
            <h1 className="ple-page__title">SUNAT PLE — Libros electrónicos</h1>
            <p className="ple-page__subtitle">
              Programa de Libros Electrónicos · Ventas 14.1, compras 8.1, diario 5.1 y mayor 6.1
            </p>
          </div>

          <div className="ple-page__toolbar">
            <div className="ple-period">
              <span>Periodo</span>
              <AppDropdown
                className="ple-period__select"
                value={month}
                options={MONTH_OPTIONS}
                onChange={(value) => updatePeriod(year, value)}
                variant="form"
                showFieldLabel={false}
              />
              <AppDropdown
                className="ple-period__select"
                value={year}
                options={yearOptions}
                onChange={(value) => updatePeriod(value, month)}
                variant="form"
                showFieldLabel={false}
              />
            </div>
            <Button
              variant="secondary"
              onClick={() => void runValidation('all')}
              disabled={busy || !periodValid}
              loading={validating === 'all'}
            >
              Validar todo
            </Button>
            <Button
              variant="primary"
              onClick={() => void downloadZip()}
              disabled={busy || !periodValid}
              loading={downloadingZip}
            >
              Descargar ZIP
            </Button>
          </div>
        </header>

        {error && <div className="ple-page__error">{error}</div>}

        {loading ? (
          <div className="ple-loading">
            <i className="ph ph-circle-notch ple-loading__icon" aria-hidden="true" />
            Cargando libros electrónicos…
          </div>
        ) : preview && (
          <>
            <div className="ple-cards">
              {bookCards.map(({ key, book }) => {
                const isActive = activeBook === key;
                const status = resolveBookStatus(book, key, validation);
                const dotClass =
                  status.tone === 'ready'
                    ? 'ple-card__dot--ready'
                    : status.tone === 'empty'
                      ? 'ple-card__dot--empty'
                      : status.tone === 'error'
                        ? 'ple-card__dot--error'
                        : '';

                return (
                  <button
                    key={key}
                    type="button"
                    className={`ple-card${isActive ? ' ple-card--active' : ''}`}
                    onClick={() => setActiveBook(key)}
                  >
                    <div className="ple-card__head">
                      <span className="ple-card__code">{book.pleCode}</span>
                      <span className={`ple-card__dot ${dotClass}`} aria-hidden="true" />
                    </div>
                    <span className="ple-card__label">{book.label}</span>
                    <span className="ple-card__meta">{cardMeta(book)}</span>
                  </button>
                );
              })}
            </div>

            {selectedBook && selectedStatus && (
              <section className="ple-detail" aria-label={selectedBook.label}>
                <div className="ple-detail__header">
                  <div className="ple-detail__title-wrap">
                    <h2 className="ple-detail__title">
                      {selectedBook.label}
                      <span className="ple-detail__period">{periodLabel(period)}</span>
                    </h2>
                    <span className={`ple-detail__status ple-detail__status--${selectedStatus.tone}`}>
                      {selectedStatus.tone === 'ready' && (
                        <i className="ph ph-check-circle" aria-hidden="true" />
                      )}
                      {selectedStatus.tone === 'error' && (
                        <i className="ph ph-warning-circle" aria-hidden="true" />
                      )}
                      {selectedStatus.label}
                    </span>
                  </div>

                  <div className="ple-detail__actions">
                    {validation?.validatedAt && (
                      <span className="ple-detail__generated">
                        Validado {formatGeneratedAt(validation.validatedAt)}
                      </span>
                    )}
                    {!validation?.validatedAt && preview.generatedAt && (
                      <span className="ple-detail__generated">
                        Generado {formatGeneratedAt(preview.generatedAt)}
                      </span>
                    )}
                    <Button
                      variant="secondary"
                      disabled={selectedBook.recordCount === 0 || busy}
                      loading={validating === activeBook}
                      onClick={() => void runValidation(activeBook)}
                    >
                      Validar
                    </Button>
                    <Button
                      variant="primary"
                      disabled={selectedBook.recordCount === 0 || busy}
                      loading={downloading === activeBook}
                      onClick={() => void downloadBook(activeBook)}
                    >
                      Descargar {selectedBook.filename ?? 'archivo PLE'}
                    </Button>
                  </div>
                </div>

                {journalSelected && (
                  <ErpModuleBanner>
                    Diario y Mayor salen de los asientos operativos del periodo. No incluyen apertura ni
                    cierre certificado.
                  </ErpModuleBanner>
                )}

                <div className="ple-tabs" role="tablist" aria-label="Libros con vista previa">
                  <button
                    type="button"
                    role="tab"
                    aria-selected={activeBook === 'sales'}
                    className={`ple-tab${activeBook === 'sales' ? ' ple-tab--active' : ''}`}
                    onClick={() => setActiveBook('sales')}
                  >
                    Reg. Ventas ({salesBook?.recordCount ?? 0})
                  </button>
                  <button
                    type="button"
                    role="tab"
                    aria-selected={activeBook === 'purchases'}
                    className={`ple-tab${activeBook === 'purchases' ? ' ple-tab--active' : ''}`}
                    onClick={() => setActiveBook('purchases')}
                  >
                    Reg. Compras ({purchasesBook?.recordCount ?? 0})
                  </button>
                  <button
                    type="button"
                    role="tab"
                    aria-selected={activeBook === 'diary'}
                    className={`ple-tab${activeBook === 'diary' ? ' ple-tab--active' : ''}`}
                    onClick={() => setActiveBook('diary')}
                  >
                    Diario ({diaryBook?.recordCount ?? 0})
                  </button>
                  <button
                    type="button"
                    role="tab"
                    aria-selected={activeBook === 'ledger'}
                    className={`ple-tab${activeBook === 'ledger' ? ' ple-tab--active' : ''}`}
                    onClick={() => setActiveBook('ledger')}
                  >
                    Mayor ({ledgerBook?.recordCount ?? 0})
                  </button>
                </div>

                {activeValidation && <PleIssuesPanel validation={activeValidation} />}
                {journalSelected ? (
                  <PleJournalTable book={selectedBook} />
                ) : (
                  <PleLedgerTable book={selectedBook} />
                )}
              </section>
            )}
          </>
        )}
      </div>
    </div>
  );
}