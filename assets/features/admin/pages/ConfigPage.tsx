import { useEffect, useRef, useState } from 'react';
import toast from '@shared/lib/SharedUtils';
import { configService, type ConfigDto, isTechnical } from '@features/config';
import './ConfigPage.scss';

// ── Label registry ────────────────────────────────────────────────────────────

interface ConfigMeta {
  label: string;
  description?: string;
  group: 'general' | 'sales' | 'inventory' | 'feature';
  options?: { value: string; label: string }[];  // if set → renders as <select>
}

const CONFIG_META: Record<string, ConfigMeta> = {
  app_option_igv:                    { label: 'IGV (%)',                          description: 'Porcentaje de IGV aplicado a las ventas.',                  group: 'general' },
  app_option_rc:                     { label: 'Recargo al Consumo (%)',           description: 'Porcentaje de recargo al consumo (RC) impreso en el comprobante.', group: 'general' },
  app_option_print_logo:             { label: 'Imprimir logo en comprobantes',    description: 'Muestra el logo de la empresa en los comprobantes.',        group: 'general' },
  app_option_require_cash_register:  { label: 'Requerir caja registradora',       description: 'Obliga a seleccionar una caja antes de vender.',            group: 'general' },
  app_option_receipt_required:       { label: 'Requerir boleta/factura',          description: 'Obliga a emitir un comprobante en cada venta.',             group: 'general' },
  print_method:                      {
    label: 'Método de impresión',
    description: 'Cómo se envían los comprobantes a la impresora térmica.',
    group: 'general',
    options: [
      { value: 'WEBUSB',      label: 'WebUSB (navegador, sin instalación)' },
      { value: 'LOCAL_AGENT', label: 'Agente eFact (app local instalada)' },
    ],
  },
  app_option_auto_invoice:           { label: 'Facturación automática',           description: 'Emite el comprobante electrónico automáticamente.',         group: 'sales' },
  sales_default_currency:            { label: 'Moneda por defecto',              description: 'Moneda predeterminada para nuevas ventas.',                 group: 'sales' },
  sales_default_usd_exchange_rate:   { label: 'Tipo de cambio USD por defecto',  description: 'Valor inicial del tipo de cambio USD/PEN.',                group: 'sales' },
  inventory_negative_stock_policy:   {
    label: 'Política de stock negativo',
    description: 'Define qué ocurre cuando el stock llega a cero.',
    group: 'inventory',
    options: [
      { value: 'BLOCK',          label: 'Bloquear venta (no permite stock negativo)' },
      { value: 'ALLOW_WITH_ROLE', label: 'Permitir solo a administradores' },
      { value: 'ALLOW_ALL',      label: 'Permitir a todos los usuarios' },
    ],
  },
};

function getMeta(key: string): ConfigMeta {
  return CONFIG_META[key] ?? { label: key, group: 'general' };
}

// ── Sub-components ────────────────────────────────────────────────────────────

interface SettingRowProps {
  config: ConfigDto;
  onSave: (config: ConfigDto, newValue: string) => Promise<void>;
}

const SettingRow = ({ config, onSave }: SettingRowProps) => {
  const meta = getMeta(config.name);
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(config.value);
  const [saving, setSaving] = useState(false);
  const inputRef = useRef<HTMLInputElement | HTMLSelectElement>(null);

  // Sync draft when config changes externally
  useEffect(() => {
    if (!editing) setDraft(config.value);
  }, [config.value, editing]);

  const handleEdit = () => {
    setDraft(config.value);
    setEditing(true);
    setTimeout(() => inputRef.current?.focus(), 50);
  };

  const handleCancel = () => {
    setDraft(config.value);
    setEditing(false);
  };

  const handleSave = async () => {
    if (draft === config.value) { setEditing(false); return; }
    setSaving(true);
    try {
      await onSave(config, draft);
      setEditing(false);
    } finally {
      setSaving(false);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') void handleSave();
    if (e.key === 'Escape') handleCancel();
  };

  // Bool configs that are NOT feature flags get a bool select
  const isBool = config.type === 'bool' || config.value === 'true' || config.value === 'false';
  // Enum configs have a fixed options list in meta
  const isEnum = Boolean(meta.options?.length);

  // Display label for current value
  const displayValue = isEnum
    ? (meta.options!.find((o) => o.value === config.value)?.label ?? config.value)
    : isBool
      ? (config.value === 'true' ? 'Sí' : 'No')
      : config.value;

  return (
    <div className="config-setting-row">
      <div className="config-setting-info">
        <span className="config-setting-label">{meta.label}</span>
        {meta.description && (
          <span className="config-setting-description">{meta.description}</span>
        )}
      </div>
      <div className="config-setting-value">
        {editing ? (
          <div className="config-setting-edit">
            {isEnum ? (
              <select
                ref={inputRef as React.RefObject<HTMLSelectElement>}
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                onKeyDown={handleKeyDown}
                className="config-setting-input"
              >
                {meta.options!.map((o) => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </select>
            ) : isBool ? (
              <select
                ref={inputRef as React.RefObject<HTMLSelectElement>}
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                onKeyDown={handleKeyDown}
                className="config-setting-input"
              >
                <option value="true">Sí</option>
                <option value="false">No</option>
              </select>
            ) : (
              <input
                ref={inputRef as React.RefObject<HTMLInputElement>}
                type={config.type === 'int' || config.type === 'float' ? 'number' : 'text'}
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                onKeyDown={handleKeyDown}
                className="config-setting-input"
              />
            )}
            <button
              type="button"
              className="config-btn config-btn--primary"
              onClick={() => void handleSave()}
              disabled={saving}
            >
              {saving ? '...' : 'Guardar'}
            </button>
            <button
              type="button"
              className="config-btn config-btn--ghost"
              onClick={handleCancel}
              disabled={saving}
            >
              Cancelar
            </button>
          </div>
        ) : (
          <div className="config-setting-display">
            <span className="config-setting-current">{displayValue}</span>
            <button
              type="button"
              className="config-btn config-btn--ghost"
              onClick={handleEdit}
            >
              Editar
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

interface SettingGroupProps {
  title: string;
  configs: ConfigDto[];
  onSave: (config: ConfigDto, newValue: string) => Promise<void>;
}

const SettingGroup = ({ title, configs, onSave }: SettingGroupProps) => {
  if (configs.length === 0) return null;
  return (
    <section className="config-group">
      <h3 className="config-group-title">{title}</h3>
      <div className="config-group-rows">
        {configs.map((c) => (
          <SettingRow key={c.id} config={c} onSave={onSave} />
        ))}
      </div>
    </section>
  );
};

// ── Main page ─────────────────────────────────────────────────────────────────

export const ConfigPage = () => {
  const [configs, setConfigs] = useState<ConfigDto[]>([]);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const data = await configService.getAll();
      setConfigs(data);
    } catch {
      toast('Error al cargar las configuraciones', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { void load(); }, []);

  const handleSave = async (config: ConfigDto, newValue: string) => {
    const updated = await configService.patch(config.id, newValue);
    setConfigs((prev) => prev.map((c) => (c.id === updated.id ? updated : c)));
    toast('Configuración guardada', 'success');
  };

  // Partition configs — hide technical migration keys
  const visible = configs.filter((c) => !isTechnical(c.name));

  const general   = visible.filter((c) => getMeta(c.name).group === 'general');
  const sales     = visible.filter((c) => getMeta(c.name).group === 'sales');
  const inventory = visible.filter((c) => getMeta(c.name).group === 'inventory');
  const other     = visible.filter((c) => !CONFIG_META[c.name]);

  return (
    <div className="view-wrapper-scroll">
    <div className="config-page">
      <div className="config-page-header">
        <h2 className="config-page-title">Configuraciones</h2>
        <p className="config-page-subtitle">
          Ajusta el comportamiento de la aplicación para tu empresa.
        </p>
      </div>

      <div className="config-content">
        {loading ? (
          <div className="config-loading">
            <i className="ph ph-circle-notch config-loading-icon" />
            Cargando configuraciones…
          </div>
        ) : (
          <>
            <SettingGroup title="General"    configs={general}   onSave={handleSave} />
            <SettingGroup title="Ventas"     configs={sales}     onSave={handleSave} />
            <SettingGroup title="Inventario" configs={inventory} onSave={handleSave} />
            {other.length > 0 && (
              <SettingGroup title="Otras" configs={other} onSave={handleSave} />
            )}
            {visible.length === 0 && (
              <p className="config-empty">No hay configuraciones disponibles.</p>
            )}
          </>
        )}
      </div>
    </div>
    </div>
  );
};
