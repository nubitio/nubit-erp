import { useState } from 'react';
import toast from '@shared/lib/SharedUtils';
import { useTenantIgvRate } from '@shared/hooks/useTenantIgvRate';
import type { IgvSummaryResponse } from './api/taxesEndpoints';
import { useIgvSummary, useDetracciones, useRetencionesPercepciones } from './useTaxes';
import { formatDate } from '@shared/lib/displayFormat';
import './TaxesPage.scss';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------
type TabKey = 'igv' | 'detracciones' | 'retenciones';

const TABS: { key: TabKey; label: string }[] = [
  { key: 'igv', label: 'IGV mensual' },
  { key: 'detracciones', label: 'Detracciones' },
  { key: 'retenciones', label: 'Retenciones y percepciones' },
];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function fmt(amount: number): string {
  return amount.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtMoney(amount: number): string {
  return `S/ ${fmt(amount)}`;
}

function fmtRatePercent(rate: number): string {
  return `${(rate * 100).toFixed(1).replace(/\.0$/, '')}%`;
}

// ---------------------------------------------------------------------------
// KPI cards
// ---------------------------------------------------------------------------
interface KpiCardProps {
  label: string;
  value: string;
  icon: string;
  tone?: 'danger' | 'info' | 'default';
  loading?: boolean;
}

function KpiCard({ label, value, icon, tone = 'default', loading }: KpiCardProps) {
  if (loading) {
    return (
      <div className="taxes-kpi">
        <div className="taxes-kpi__header">
          <span className="taxes-kpi__label">{label}</span>
        </div>
        <span className="taxes-kpi__value taxes-kpi__value--loading">—</span>
      </div>
    );
  }

  return (
    <div className={`taxes-kpi taxes-kpi--${tone}`}>
      <div className="taxes-kpi__header">
        <span className="taxes-kpi__label">{label}</span>
        <i className={`ph ${icon} taxes-kpi__icon`} aria-hidden="true" />
      </div>
      <span className="taxes-kpi__value">{value}</span>
    </div>
  );
}

// ---------------------------------------------------------------------------
// IGV tab
// ---------------------------------------------------------------------------
interface FiscalCardProps {
  title: string;
  icon: string;
  iconTone: 'up' | 'down';
  baseImponible: number;
  igv: number;
  igvLabel: string;
  loading?: boolean;
}

function FiscalCard({
  title,
  icon,
  iconTone,
  baseImponible,
  igv,
  igvLabel,
  loading,
}: FiscalCardProps) {
  return (
    <div className="taxes-card">
      <div className="taxes-card__header">
        <span className={`taxes-card__icon-wrap taxes-card__icon-wrap--${iconTone}`}>
          <i className={`ph ${icon}`} aria-hidden="true" />
        </span>
        <span className="taxes-card__title">{title}</span>
      </div>
      <div className="taxes-card__rows">
        <div className="taxes-card__row">
          <span className="taxes-card__row-label">Base imponible</span>
          <span className="taxes-card__row-value">{loading ? '—' : fmtMoney(baseImponible)}</span>
        </div>
        <div className="taxes-card__row">
          <span className="taxes-card__row-label">{igvLabel}</span>
          <span className={`taxes-card__row-value taxes-card__row-value--accent`}>
            {loading ? '—' : fmtMoney(igv)}
          </span>
        </div>
      </div>
    </div>
  );
}

function HistorialTable({
  rows,
  loading,
}: {
  rows: IgvSummaryResponse['historial'];
  loading?: boolean;
}) {
  if (loading) {
    return (
      <div className="taxes-card">
        <div className="taxes-historial__title">Historial de declaraciones</div>
        <div className="taxes-loading">Cargando historial…</div>
      </div>
    );
  }

  return (
    <div className="taxes-card">
      <div className="taxes-historial__title">Historial de declaraciones</div>
      <div className="taxes-table-wrap">
        <table className="taxes-table">
          <thead>
            <tr>
              <th>PERIODO</th>
              <th>IGV VENTAS</th>
              <th>IGV COMPRAS</th>
              <th>IGV A PAGAR</th>
              <th>ESTADO</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.periodo}>
                <td>{row.label}</td>
                <td>{fmtMoney(row.igvVentas)}</td>
                <td>{fmtMoney(row.igvCompras)}</td>
                <td className="taxes-table__pagar">{fmtMoney(row.igvPagar)}</td>
                <td>
                  <span className={`taxes-badge taxes-badge--${row.estado}`}>
                    <span className="taxes-badge__dot" aria-hidden="true" />
                    {row.estado === 'abierto' ? 'ABIERTO' : 'DECLARADO'}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function LiquidacionPanel({
  data,
  loading,
}: {
  data: IgvSummaryResponse | undefined;
  loading: boolean;
}) {
  const handleGeneratePdt = () => {
    toast('La generación del PDT estará disponible en una próxima versión.', 'info', 3000);
  };

  return (
    <div className="taxes-liquidacion">
      <div className="taxes-liquidacion__head">
        <div className="taxes-liquidacion__title">Liquidación de IGV</div>
        <div className="taxes-liquidacion__subtitle">
          {loading ? '…' : `${data?.periodLabel ?? ''} · Formulario 621`}
        </div>
      </div>

      <div className="taxes-liquidacion__rows">
        <div className="taxes-liquidacion__row">
          <span className="taxes-liquidacion__row-label">IGV débito fiscal</span>
          <span className="taxes-liquidacion__row-value">
            {loading ? '—' : fmtMoney(data?.igvMensual.debitoFiscal.igv ?? 0)}
          </span>
        </div>
        <div className="taxes-liquidacion__row taxes-liquidacion__row--deduct">
          <span className="taxes-liquidacion__row-label">IGV crédito fiscal</span>
          <span className="taxes-liquidacion__row-value taxes-liquidacion__row-value--blue">
            {loading ? '—' : `- ${fmtMoney(data?.igvMensual.creditoFiscal.igv ?? 0)}`}
          </span>
        </div>
        <div className="taxes-liquidacion__row taxes-liquidacion__row--deduct">
          <span className="taxes-liquidacion__row-label">Retenciones / percepciones</span>
          <span className="taxes-liquidacion__row-value taxes-liquidacion__row-value--muted">
            {loading ? '—' : `- ${fmtMoney(data?.igvMensual.retencionesSufridas ?? 0)}`}
          </span>
        </div>
      </div>

      <div className="taxes-liquidacion__divider" />

      <div className="taxes-liquidacion__total">
        <span className="taxes-liquidacion__total-label">IGV por pagar</span>
        <span className="taxes-liquidacion__total-value">
          {loading ? '—' : fmtMoney(data?.igvMensual.igvPorPagar ?? 0)}
        </span>
      </div>

      <div className="taxes-liquidacion__due">
        <i className="ph ph-clock taxes-liquidacion__due-icon" aria-hidden="true" />
        <span>Vence el {loading ? '…' : formatDate(data?.vencimiento ?? '')}</span>
      </div>

      <button
        type="button"
        className="taxes-liquidacion__cta"
        disabled={loading}
        onClick={handleGeneratePdt}
      >
        <i className="ph ph-paper-plane-tilt" aria-hidden="true" />
        Generar declaración PDT
      </button>
    </div>
  );
}

function IgvTab({ period }: { period?: string }) {
  const { data, isLoading } = useIgvSummary(period);
  const { igvRate } = useTenantIgvRate();
  const igvLabel = `IGV ${fmtRatePercent(igvRate)}`;

  return (
    <div className="taxes-igv-layout">
      <div className="taxes-igv-main">
        <div className="taxes-igv-fiscales">
          <FiscalCard
            title="Débito fiscal (ventas)"
            icon="ph-arrow-fat-up"
            iconTone="up"
            baseImponible={data?.igvMensual.debitoFiscal.baseImponible ?? 0}
            igv={data?.igvMensual.debitoFiscal.igv ?? 0}
            igvLabel={igvLabel}
            loading={isLoading}
          />
          <FiscalCard
            title="Crédito fiscal (compras)"
            icon="ph-arrow-fat-down"
            iconTone="down"
            baseImponible={data?.igvMensual.creditoFiscal.baseImponible ?? 0}
            igv={data?.igvMensual.creditoFiscal.igv ?? 0}
            igvLabel={igvLabel}
            loading={isLoading}
          />
        </div>
        <HistorialTable rows={data?.historial ?? []} loading={isLoading} />
      </div>

      <aside className="taxes-igv-sidebar">
        <LiquidacionPanel data={data} loading={isLoading} />
      </aside>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Detracciones tab
// ---------------------------------------------------------------------------
function DetraccionesTab({ period, periodLabel }: { period?: string; periodLabel?: string }) {
  const { data, isLoading } = useDetracciones(period);

  if (isLoading) {
    return <div className="taxes-loading">Cargando detracciones…</div>;
  }

  const items = data?.items ?? [];

  return (
    <div className="taxes-det-layout">
      {/* Summary cards */}
      <div className="taxes-kpi-grid">
        <KpiCard
          label="DETRACCIONES POR DEPOSITAR"
          value={fmtMoney(data?.pendiente ?? 0)}
          icon="ph-warning-circle"
          tone="danger"
        />
        <KpiCard
          label="DETRACCIONES DEPOSITADAS"
          value={fmtMoney(data?.depositado ?? 0)}
          icon="ph-check-circle"
          tone="info"
        />
        <KpiCard label="CUENTA DETRACCIONES" value={data?.cuenta ?? '—'} icon="ph-bank" />
      </div>

      {/* Table */}
      <div className="taxes-card">
        <div className="taxes-historial__title">
          Comprobantes con detracción · {periodLabel ?? 'Periodo actual'}
        </div>
        <div className="taxes-table-wrap">
          <table className="taxes-table">
            <thead>
              <tr>
                <th>COMPROBANTE</th>
                <th>CLIENTE</th>
                <th>CONCEPTO</th>
                <th>SPOT %</th>
                <th>BASE</th>
                <th>DETRACCIÓN</th>
                <th>ESTADO</th>
              </tr>
            </thead>
            <tbody>
              {items.length === 0 ? (
                <tr>
                  <td colSpan={7} className="taxes-table__empty">
                    No hay detracciones en este periodo.
                  </td>
                </tr>
              ) : (
                items.map((item, i) => (
                  <tr key={`${item.series}-${item.number}-${i}`}>
                    <td>
                      {item.series}-{item.number}
                    </td>
                    <td className="taxes-table__name" title={item.cliente}>
                      {item.cliente}
                    </td>
                    <td>{item.concepto}</td>
                    <td>{item.spot_percent}%</td>
                    <td>{fmtMoney(item.base)}</td>
                    <td className="taxes-table__pagar">{fmtMoney(item.detraccion)}</td>
                    <td>
                      <span className={`taxes-badge taxes-badge--${item.estado}`}>
                        <span className="taxes-badge__dot" aria-hidden="true" />
                        {item.estado === 'depositado' ? 'DEPOSITADO' : 'PENDIENTE'}
                      </span>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Retenciones y percepciones tab
// ---------------------------------------------------------------------------
function RetencionesTab({ period, periodLabel }: { period?: string; periodLabel?: string }) {
  const { data, isLoading } = useRetencionesPercepciones(period);

  if (isLoading) {
    return <div className="taxes-loading">Cargando retenciones y percepciones…</div>;
  }

  const items = data?.items ?? [];

  return (
    <div className="taxes-ret-layout">
      <div className="taxes-card">
        <div className="taxes-historial__title">
          Retenciones y percepciones · {periodLabel ?? 'Periodo actual'}
        </div>
        <div className="taxes-table-wrap">
          <table className="taxes-table">
            <thead>
              <tr>
                <th>TIPO</th>
                <th>DIRECCIÓN</th>
                <th>CONTRAPARTE</th>
                <th>DOCUMENTO</th>
                <th>TASA %</th>
                <th>BASE</th>
                <th>MONTO</th>
              </tr>
            </thead>
            <tbody>
              {items.length === 0 ? (
                <tr>
                  <td colSpan={7} className="taxes-table__empty">
                    No hay retenciones ni percepciones en este periodo.
                  </td>
                </tr>
              ) : (
                items.map((item, i) => (
                  <tr key={`${item.document_reference}-${i}`}>
                    <td>
                      <span
                        className={`taxes-badge taxes-badge--${item.type === 'retencion' ? 'declarado' : 'retencion'}`}
                      >
                        {capitalize(item.type)}
                      </span>
                    </td>
                    <td>{capitalize(item.direction)}</td>
                    <td className="taxes-table__name" title={item.counterparty_name}>
                      {item.counterparty_name}
                    </td>
                    <td>{item.document_reference}</td>
                    <td>{item.rate}%</td>
                    <td>{fmtMoney(item.base_amount)}</td>
                    <td className="taxes-table__pagar">{fmtMoney(item.amount)}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

function capitalize(s: string): string {
  return s.charAt(0).toUpperCase() + s.slice(1);
}

export function TaxesPage() {
  const [activeTab, setActiveTab] = useState<TabKey>('igv');
  const { data: igvData, isLoading: igvLoading } = useIgvSummary();

  return (
    <div className="view-wrapper-scroll">
      <div className="taxes-page">
        {/* KPI summary strip */}
        <div className="taxes-kpi-grid">
          <KpiCard
            label={`IGV POR PAGAR${igvData?.periodLabel ? ` — ${igvData.periodLabel.toUpperCase()}` : ''}`}
            value={fmtMoney(igvData?.kpi.igvPorPagar ?? 0)}
            icon="ph-percent"
            tone="danger"
            loading={igvLoading}
          />
          <KpiCard
            label="CRÉDITO FISCAL"
            value={fmtMoney(igvData?.kpi.creditoFiscal ?? 0)}
            icon="ph-arrow-fat-down"
            tone="info"
            loading={igvLoading}
          />
          <KpiCard
            label="DETRACCIONES PENDIENTES"
            value={fmtMoney(igvData?.kpi.detraccionesPendientes ?? 0)}
            icon="ph-link"
            loading={igvLoading}
          />
          <KpiCard
            label="PRÓXIMO VENCIMIENTO"
            value={igvData?.vencimiento ? formatDate(igvData.vencimiento) : '—'}
            icon="ph-calendar"
            loading={igvLoading}
          />
        </div>

        {/* Tab bar */}
        <div className="taxes-tabs" role="tablist" aria-label="Secciones de impuestos">
          {TABS.map((tab) => (
            <button
              key={tab.key}
              type="button"
              role="tab"
              aria-selected={activeTab === tab.key}
              className={`taxes-tab${activeTab === tab.key ? ' taxes-tab--active' : ''}`}
              onClick={() => setActiveTab(tab.key)}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {/* Tab content */}
        {activeTab === 'igv' && <IgvTab />}
        {activeTab === 'detracciones' && <DetraccionesTab periodLabel={igvData?.periodLabel} />}
        {activeTab === 'retenciones' && <RetencionesTab periodLabel={igvData?.periodLabel} />}
      </div>
    </div>
  );
}
