/* eslint-disable react-hooks/set-state-in-effect -- the selected period is synchronized from loaded periods */
import { useEffect, useMemo, useState } from 'react';
import { Badge, Combobox } from '@nubitio/ui';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { ErpModuleBanner } from '@features/shared/ErpModuleBanner';
import { formatDate } from '@shared/lib/displayFormat';
import './AccountingPage.scss';

const API = getCoreApiBaseUrl();

type TabKey = 'ledger' | 'trial' | 'journal';

type LedgerResponse = {
  account: { code: string; name: string; type: string } | null;
  from: string;
  to: string;
  opening: string;
  closing: string;
  lines: Array<{
    date: string;
    entryNo: string;
    description: string;
    debit: string;
    credit: string;
    balance: string;
  }>;
};

type TrialBalanceResponse = {
  period: string;
  from: string;
  to: string;
  totalDebit: string;
  totalCredit: string;
  balanced: boolean;
  accounts: Array<{
    code: string;
    name: string;
    debit: string;
    credit: string;
    deudor: string;
    acreedor: string;
  }>;
};

type JournalRow = {
  id: string;
  entryNo: string;
  entryDate: string;
  description: string;
  source: string;
  lines?: Array<{ debit: string; credit: string; account?: { code?: string; name?: string } }>;
};

const TABS: { key: TabKey; label: string }[] = [
  { key: 'ledger', label: 'Libro mayor' },
  { key: 'trial', label: 'Balance de comprobación' },
  { key: 'journal', label: 'Diario' },
];

const LEDGER_ACCOUNTS = [
  { code: '101', name: 'Caja' },
  { code: '121', name: 'Facturas por cobrar' },
  { code: '201', name: 'Mercaderías' },
  { code: '40111', name: 'IGV' },
  { code: '421', name: 'Facturas por pagar' },
  { code: '70111', name: 'Ventas' },
  { code: '691', name: 'Costo de ventas' },
  { code: '659', name: 'Otros gastos' },
];

function money(value: string | number | undefined): string {
  const amount = Number(value ?? 0);
  return `S/ ${amount.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

const MONTH_OPTIONS = [
  'Enero',
  'Febrero',
  'Marzo',
  'Abril',
  'Mayo',
  'Junio',
  'Julio',
  'Agosto',
  'Septiembre',
  'Octubre',
  'Noviembre',
  'Diciembre',
];

function currentPeriod(): string {
  const now = new Date();
  return `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}`;
}

function monthBounds(period: string): { from: string; to: string } {
  const year = Number(period.slice(0, 4));
  const month = Number(period.slice(4, 6));
  const from = `${year}-${String(month).padStart(2, '0')}-01`;
  const last = new Date(year, month, 0).getDate();
  const to = `${year}-${String(month).padStart(2, '0')}-${String(last).padStart(2, '0')}`;
  return { from, to };
}

export function AccountingPage() {
  const [tab, setTab] = useState<TabKey>('ledger');
  const [period, setPeriod] = useState(currentPeriod());
  const [account, setAccount] = useState('101');
  const [ledger, setLedger] = useState<LedgerResponse | null>(null);
  const [trial, setTrial] = useState<TrialBalanceResponse | null>(null);
  const [journal, setJournal] = useState<JournalRow[]>([]);
  const [loading, setLoading] = useState(false);

  const bounds = useMemo(() => monthBounds(period), [period]);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);

    const load = async () => {
      if (tab === 'ledger') {
        const response = await httpClient.get<LedgerResponse>(
          `${API}general-ledger/ledger?account=${encodeURIComponent(account)}&from=${bounds.from}&to=${bounds.to}`,
        );
        if (!cancelled) setLedger(response.data);
      }
      if (tab === 'trial') {
        const response = await httpClient.get<TrialBalanceResponse>(
          `${API}general-ledger/trial-balance?period=${period}`,
        );
        if (!cancelled) setTrial(response.data);
      }
      if (tab === 'journal') {
        const response = await httpClient.get<{ 'hydra:member'?: JournalRow[] } | JournalRow[]>(
          `${API}journal-entries?order[entryDate]=desc&itemsPerPage=50`,
        );
        const payload = response.data;
        const rows = Array.isArray(payload) ? payload : (payload['hydra:member'] ?? []);
        if (!cancelled) setJournal(rows);
      }
    };

    load()
      .catch(() => {
        if (tab === 'ledger') setLedger(null);
        if (tab === 'trial') setTrial(null);
        if (tab === 'journal') setJournal([]);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [tab, account, bounds.from, bounds.to, period]);

  return (
    <div className="view-wrapper-scroll">
      <div className="acc-page">
        <div className="acc-page__header">
          <div>
            <h1 className="acc-page__title">Contabilidad</h1>
            <p className="acc-page__subtitle">
              Libro mayor de partida doble. Los asientos salen solos de ventas, compras y caja.
            </p>
          </div>
          <label className="acc-period">
            Periodo
            <span className="acc-period__fields">
              <Combobox
                aria-label="Mes"
                clearable={false}
                options={MONTH_OPTIONS.map((name, index) => ({
                  value: String(index + 1).padStart(2, '0'),
                  label: name,
                }))}
                value={period.slice(4, 6)}
                onChange={(month) => month && setPeriod(`${period.slice(0, 4)}${month}`)}
              />
              <Combobox
                aria-label="Año"
                clearable={false}
                options={Array.from({ length: 6 }, (_, index) => {
                  const year = String(new Date().getFullYear() - 3 + index);
                  return { value: year, label: year };
                })}
                value={period.slice(0, 4)}
                onChange={(year) => year && setPeriod(`${year}${period.slice(4, 6)}`)}
              />
            </span>
          </label>
        </div>

        <ErpModuleBanner>
          Esto es el mayor operativo del negocio, no un cierre contable certificado. Sirve para ver
          si caja, inventario, IGV y resultados cuadran.
        </ErpModuleBanner>

        <div className="acc-tabs" role="tablist" aria-label="Libros contables">
          {TABS.map((item) => (
            <button
              key={item.key}
              type="button"
              role="tab"
              aria-selected={tab === item.key}
              className={`acc-tab${tab === item.key ? ' acc-tab--active' : ''}`}
              onClick={() => setTab(item.key)}
            >
              {item.label}
            </button>
          ))}
        </div>

        {tab === 'ledger' && (
          <div className="acc-card">
            <div className="acc-toolbar">
              <label>
                Cuenta
                <Combobox
                  aria-label="Cuenta"
                  clearable={false}
                  options={LEDGER_ACCOUNTS.map((item) => ({
                    value: item.code,
                    label: `${item.code} · ${item.name}`,
                  }))}
                  value={account}
                  onChange={(code) => code && setAccount(code)}
                />
              </label>
              {ledger?.account && (
                <div className="acc-balances">
                  <span>Saldo inicial {money(ledger.opening)}</span>
                  <strong>Saldo {money(ledger.closing)}</strong>
                </div>
              )}
            </div>
            {loading ? (
              <p className="acc-empty">Cargando…</p>
            ) : (ledger?.lines.length ?? 0) === 0 ? (
              <p className="acc-empty">Aún no hay movimientos en esta cuenta para el periodo.</p>
            ) : (
              <table className="acc-table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Asiento</th>
                    <th>Glosa</th>
                    <th>Debe</th>
                    <th>Haber</th>
                    <th>Saldo</th>
                  </tr>
                </thead>
                <tbody>
                  {ledger?.lines.map((line) => (
                    <tr key={`${line.entryNo}-${line.date}-${line.debit}-${line.credit}`}>
                      <td>{formatDate(line.date)}</td>
                      <td>{line.entryNo}</td>
                      <td>{line.description}</td>
                      <td className="acc-table__amount">
                        {Number(line.debit) ? money(line.debit) : ''}
                      </td>
                      <td className="acc-table__amount">
                        {Number(line.credit) ? money(line.credit) : ''}
                      </td>
                      <td className="acc-table__amount">{money(line.balance)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}

        {tab === 'trial' && (
          <div className="acc-card">
            <div className="acc-toolbar">
              {trial && (
                <Badge variant={trial.balanced ? 'success' : 'danger'} outlined>
                  {trial.balanced ? 'Cuadra' : 'No cuadra'}
                </Badge>
              )}
              {trial && (
                <div className="acc-balances">
                  <span>Debe {money(trial.totalDebit)}</span>
                  <span>Haber {money(trial.totalCredit)}</span>
                </div>
              )}
            </div>
            {loading ? (
              <p className="acc-empty">Cargando…</p>
            ) : (trial?.accounts.length ?? 0) === 0 ? (
              <p className="acc-empty">
                Todavía no hay asientos en este periodo. Vende o compra para generarlos.
              </p>
            ) : (
              <table className="acc-table">
                <thead>
                  <tr>
                    <th>Cuenta</th>
                    <th>Debe</th>
                    <th>Haber</th>
                    <th>Deudor</th>
                    <th>Acreedor</th>
                  </tr>
                </thead>
                <tbody>
                  {trial?.accounts.map((row) => (
                    <tr key={row.code}>
                      <td>
                        {row.code} · {row.name}
                      </td>
                      <td className="acc-table__amount">{money(row.debit)}</td>
                      <td className="acc-table__amount">{money(row.credit)}</td>
                      <td className="acc-table__amount">
                        {Number(row.deudor) ? money(row.deudor) : ''}
                      </td>
                      <td className="acc-table__amount">
                        {Number(row.acreedor) ? money(row.acreedor) : ''}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}

        {tab === 'journal' && (
          <div className="acc-card">
            {loading ? (
              <p className="acc-empty">Cargando…</p>
            ) : journal.length === 0 ? (
              <p className="acc-empty">
                El diario se llena solo con cada venta, compra o movimiento de caja.
              </p>
            ) : (
              <table className="acc-table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Asiento</th>
                    <th>Origen</th>
                    <th>Glosa</th>
                  </tr>
                </thead>
                <tbody>
                  {journal.map((row) => (
                    <tr key={row.id}>
                      <td>{row.entryDate}</td>
                      <td>{row.entryNo}</td>
                      <td>{row.source}</td>
                      <td>{row.description}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
