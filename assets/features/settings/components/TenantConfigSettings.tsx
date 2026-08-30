import { useEffect, useRef, useState, type KeyboardEvent, type RefObject } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { AppDropdown, Button, TextField, Toggle } from '@nubitio/ui';
import {
  ConfigKey,
  configService,
  type ConfigDto,
  isTechnical,
  tenantSettingsService,
  type TenantSettingsDto,
} from '@features/config';
import { settings } from '@shared/settings';
import { TENANT_RUNTIME_CONFIG_QUERY_KEY } from '@shared/hooks';
import toast from '@shared/lib/SharedUtils';
import '../../admin/ConfigPage.scss';

interface ConfigMeta {
  label: string;
  description?: string;
  group: 'general' | 'sales' | 'inventory' | 'feature';
  options?: { value: string; label: string }[];
}

const CONFIG_META: Record<string, ConfigMeta> = {
  app_option_igv: {
    label: 'IGV (%)',
    description: 'Porcentaje de IGV aplicado a las ventas.',
    group: 'general',
  },
  app_option_rc: {
    label: 'Recargo al Consumo (%)',
    description: 'Porcentaje de recargo al consumo (RC) impreso en el comprobante.',
    group: 'general',
  },
  app_option_print_logo: {
    label: 'Imprimir logo en comprobantes',
    description: 'Muestra el logo de la empresa en los comprobantes.',
    group: 'general',
  },
  app_option_require_cash_register: {
    label: 'Requerir caja registradora',
    description: 'Obliga a seleccionar una caja antes de vender.',
    group: 'general',
  },
  app_option_receipt_required: {
    label: 'Requerir boleta/factura',
    description: 'Obliga a emitir un comprobante en cada venta.',
    group: 'general',
  },
  print_method: {
    label: 'Método de impresión',
    description: 'Cómo se envían los comprobantes a la impresora térmica.',
    group: 'general',
    options: [
      { value: 'WEBUSB', label: 'WebUSB (navegador, sin instalación)' },
      { value: 'LOCAL_AGENT', label: 'Agente eFact (hardware local: impresora y balanza)' },
    ],
  },
  sales_default_currency: {
    label: 'Moneda por defecto',
    description: 'Moneda predeterminada para nuevas ventas.',
    group: 'sales',
  },
  sales_default_usd_exchange_rate: {
    label: 'Tipo de cambio USD por defecto',
    description: 'Valor inicial del tipo de cambio USD/PEN.',
    group: 'sales',
  },
  inventory_negative_stock_policy: {
    label: 'Política de stock negativo',
    description: 'Define qué ocurre cuando el stock llega a cero.',
    group: 'inventory',
    options: [
      { value: 'BLOCK', label: 'Bloquear venta (no permite stock negativo)' },
      { value: 'ALLOW_WITH_ROLE', label: 'Permitir solo a administradores' },
      { value: 'ALLOW_ALL', label: 'Permitir a todos los usuarios' },
    ],
  },
};

function getMeta(key: string): ConfigMeta {
  return CONFIG_META[key] ?? { label: key, group: 'general' };
}

function syncIgvConfig(configs: ConfigDto[], igv: number): ConfigDto[] {
  const igvValue = String(igv);
  let found = false;

  const synced = configs.map((config) => {
    if (config.name !== ConfigKey.IGV) return config;
    found = true;
    return { ...config, value: igvValue, type: 'float' as const };
  });

  if (found) return synced;

  return [
    ...synced,
    {
      id: -1,
      name: ConfigKey.IGV,
      value: igvValue,
      type: 'float',
    },
  ];
}

interface SettingRowProps {
  config: ConfigDto;
  onSave: (config: ConfigDto, newValue: string) => Promise<void>;
}

const SettingRow = ({ config, onSave }: SettingRowProps) => {
  const meta = getMeta(config.name);
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(config.value);
  const [saving, setSaving] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (editing) return;
    queueMicrotask(() => setDraft(config.value));
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
    if (draft === config.value) {
      setEditing(false);
      return;
    }
    setSaving(true);
    try {
      await onSave(config, draft);
      setEditing(false);
    } finally {
      setSaving(false);
    }
  };

  const handleKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Enter') void handleSave();
    if (e.key === 'Escape') handleCancel();
  };

  const isBool = config.type === 'bool' || config.value === 'true' || config.value === 'false';
  const isEnum = Boolean(meta.options?.length);

  const displayValue = isEnum
    ? (meta.options!.find((o) => o.value === config.value)?.label ?? config.value)
    : isBool
      ? config.value === 'true'
        ? 'Sí'
        : 'No'
      : config.value;

  return (
    <div className="config-setting-row">
      <div className="config-setting-info">
        <span className="config-setting-label">{meta.label}</span>
        {meta.description && <span className="config-setting-description">{meta.description}</span>}
      </div>
      <div className="config-setting-value">
        {editing ? (
          <div className="config-setting-edit">
            {isEnum || isBool ? (
              <AppDropdown
                value={draft}
                options={
                  isEnum
                    ? meta.options!.map((option) => ({
                        value: option.value,
                        label: option.label,
                      }))
                    : [
                        { value: 'true', label: 'Sí' },
                        { value: 'false', label: 'No' },
                      ]
                }
                onChange={setDraft}
                variant="form"
                showFieldLabel={false}
                className="config-setting-input"
              />
            ) : (
              <TextField
                ref={inputRef as RefObject<HTMLInputElement>}
                type={config.type === 'int' || config.type === 'float' ? 'number' : 'text'}
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                onKeyDown={handleKeyDown}
                className="config-setting-input"
              />
            )}
            <Button
              variant="primary"
              className="config-btn config-btn--primary"
              onClick={() => void handleSave()}
              loading={saving}
            >
              Guardar
            </Button>
            <Button
              variant="ghost"
              className="config-btn config-btn--ghost"
              onClick={handleCancel}
              disabled={saving}
            >
              Cancelar
            </Button>
          </div>
        ) : (
          <div className="config-setting-display">
            <span className="config-setting-current">{displayValue}</span>
            <Button variant="ghost" className="config-btn config-btn--ghost" onClick={handleEdit}>
              Editar
            </Button>
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

export const SunatSettingsSection = () => {
  const [state, setState] = useState<Awaited<ReturnType<typeof tenantSettingsService.getSunatState>> | null>(null);
  const [loading, setLoading] = useState(true);
  const [toggling, setToggling] = useState(false);
  const [editingThreshold, setEditingThreshold] = useState(false);
  const [thresholdDraft, setThresholdDraft] = useState('');
  const [savingThreshold, setSavingThreshold] = useState(false);
  const thresholdRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    tenantSettingsService.getSunatState()
      .then(setState)
      .catch(() => toast('Error al cargar el estado SUNAT', 'error'))
      .finally(() => setLoading(false));
  }, []);

  const handleToggle = async () => {
    if (!state) return;
    setToggling(true);
    try {
      const updated = await tenantSettingsService.patchSunatState({ sendEnabled: !state.sendEnabled });
      setState(updated);
      toast(updated.sendEnabled ? 'Envío a SUNAT activado' : 'Envío a SUNAT pausado', 'success');
    } catch {
      toast('Error al cambiar el estado SUNAT', 'error');
    } finally {
      setToggling(false);
    }
  };

  const handleEditThreshold = () => {
    if (!state) return;
    setThresholdDraft(String(state.rejectionThreshold));
    setEditingThreshold(true);
    setTimeout(() => thresholdRef.current?.focus(), 50);
  };

  const handleSaveThreshold = async () => {
    const value = parseInt(thresholdDraft, 10);
    if (!Number.isFinite(value) || value < 1) {
      toast('El umbral debe ser un número mayor a 0', 'error');
      return;
    }
    setSavingThreshold(true);
    try {
      const updated = await tenantSettingsService.patchSunatState({ rejectionThreshold: value });
      setState(updated);
      setEditingThreshold(false);
      toast('Umbral actualizado', 'success');
    } catch {
      toast('Error al guardar el umbral', 'error');
    } finally {
      setSavingThreshold(false);
    }
  };

  if (loading) {
    return (
      <div className="settings-loading">
        <i className="ph ph-circle-notch settings-loading__icon" aria-hidden="true" />
        Cargando configuración SUNAT…
      </div>
    );
  }
  if (!state) return null;

  const streakColor = state.rejectionStreak === 0
    ? 'var(--color-text-muted)'
    : state.rejectionStreak >= state.rejectionThreshold
      ? 'var(--color-danger, #e53e3e)'
      : 'var(--color-warning, #d97706)';

  return (
    <section className="config-group">
      <h3 className="config-group-title">Envío electrónico</h3>

      <div className="config-toggle-row">
        <div className="config-toggle-info">
          <span className="config-toggle-label">Envío automático a SUNAT</span>
          <span className="config-toggle-description">
            {state.sendEnabled
              ? 'Los comprobantes se envían automáticamente a SUNAT.'
              : 'El envío está pausado. Actívalo cuando el problema esté resuelto.'}
          </span>
        </div>
        <Toggle
          checked={state.sendEnabled}
          onChange={() => void handleToggle()}
          disabled={toggling}
          aria-label="Activar/pausar envío a SUNAT"
        />
      </div>

      <div className="config-setting-row">
        <div className="config-setting-info">
          <span className="config-setting-label">Rechazos consecutivos</span>
          <span className="config-setting-description">
            El envío se pausa automáticamente al alcanzar el umbral.
          </span>
        </div>
        <div className="config-setting-value">
          <span className="config-setting-current" style={{ color: streakColor, fontWeight: 600 }}>
            {state.rejectionStreak} / {state.rejectionThreshold}
          </span>
        </div>
      </div>

      <div className="config-setting-row">
        <div className="config-setting-info">
          <span className="config-setting-label">Umbral de pausa automática</span>
          <span className="config-setting-description">
            Número de rechazos consecutivos antes de pausar el envío.
          </span>
        </div>
        <div className="config-setting-value">
          {editingThreshold ? (
            <div className="config-setting-edit">
              <TextField
                ref={thresholdRef}
                type="number"
                min={1}
                value={thresholdDraft}
                onChange={(e) => setThresholdDraft(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') void handleSaveThreshold();
                  if (e.key === 'Escape') setEditingThreshold(false);
                }}
                className="config-setting-input"
                style={{ width: 80 }}
              />
              <Button
                variant="primary"
                className="config-btn config-btn--primary"
                onClick={() => void handleSaveThreshold()}
                loading={savingThreshold}
              >
                Guardar
              </Button>
              <Button
                variant="ghost"
                className="config-btn config-btn--ghost"
                onClick={() => setEditingThreshold(false)}
                disabled={savingThreshold}
              >
                Cancelar
              </Button>
            </div>
          ) : (
            <div className="config-setting-display">
              <span className="config-setting-current">{state.rejectionThreshold}</span>
              <Button variant="ghost" className="config-btn config-btn--ghost" onClick={handleEditThreshold}>
                Editar
              </Button>
            </div>
          )}
        </div>
      </div>
    </section>
  );
};

/**
 * Point-of-sale behaviour toggles backed by the typed /api/tenant/settings
 * endpoint. These control the *defaults* the POS starts each sale with; the
 * cashier can still override per sale from the checkout panel.
 */
export const PosBehaviorSettings = () => {
  const queryClient = useQueryClient();
  const [state, setState] = useState<TenantSettingsDto | null>(null);
  const [loading, setLoading] = useState(true);
  const [savingField, setSavingField] = useState<keyof TenantSettingsDto | null>(null);

  useEffect(() => {
    tenantSettingsService
      .getSettings()
      .then(setState)
      .catch(() => toast('Error al cargar la configuración del punto de venta', 'error'))
      .finally(() => setLoading(false));
  }, []);

  const patch = async (field: 'autoInvoice' | 'autoPrint' | 'cashRoundingEnabled', value: boolean) => {
    if (!state) return;
    setSavingField(field);
    try {
      const updated = await tenantSettingsService.patchSettings({ [field]: value });
      setState(updated);
      settings.invalidate();
      void queryClient.invalidateQueries({ queryKey: [...TENANT_RUNTIME_CONFIG_QUERY_KEY] });
      toast('Configuración guardada', 'success');
    } catch {
      toast('No se pudo guardar la configuración', 'error');
    } finally {
      setSavingField(null);
    }
  };

  if (loading) {
    return (
      <div className="settings-loading">
        <i className="ph ph-circle-notch settings-loading__icon" aria-hidden="true" />
        Cargando configuración del punto de venta…
      </div>
    );
  }
  if (!state) return null;

  return (
    <section className="config-group">
      <h3 className="config-group-title">Punto de venta</h3>

      <div className="config-toggle-row">
        <div className="config-toggle-info">
          <span className="config-toggle-label">Emitir boleta/factura por defecto</span>
          <span className="config-toggle-description">
            {state.autoInvoice
              ? 'Cada venta nueva arranca con el comprobante electrónico activado. El cajero puede desactivarlo por venta.'
              : 'Las ventas nuevas arrancan sin comprobante. El cajero puede activarlo por venta.'}
          </span>
        </div>
        <Toggle
          checked={state.autoInvoice}
          onChange={(checked) => void patch('autoInvoice', checked)}
          disabled={savingField !== null}
          aria-label="Emitir boleta o factura por defecto"
        />
      </div>

      <div className="config-toggle-row">
        <div className="config-toggle-info">
          <span className="config-toggle-label">Impresión automática</span>
          <span className="config-toggle-description">
            {state.autoPrint
              ? 'El ticket se envía a la impresora automáticamente al cerrar cada venta.'
              : 'La venta se registra sin imprimir. Usa el botón "Imprimir" del panel de última venta.'}
          </span>
        </div>
        <Toggle
          checked={state.autoPrint}
          onChange={(checked) => void patch('autoPrint', checked)}
          disabled={savingField !== null}
          aria-label="Impresión automática al cerrar la venta"
        />
      </div>

      <div className="config-toggle-row">
        <div className="config-toggle-info">
          <span className="config-toggle-label">Redondeo de efectivo a S/ 0.10</span>
          <span className="config-toggle-description">
            {state.cashRoundingEnabled
              ? 'Las ventas 100% en efectivo se redondean hacia abajo a favor del cliente. Tarjeta, crédito y pagos mixtos mantienen céntimos exactos.'
              : 'Las ventas conservan céntimos exactos. Actívalo solo si este negocio aplica redondeo en efectivo.'}
          </span>
        </div>
        <Toggle
          checked={state.cashRoundingEnabled}
          onChange={(checked) => void patch('cashRoundingEnabled', checked)}
          disabled={savingField !== null}
          aria-label="Redondeo de efectivo a diez céntimos"
        />
      </div>
    </section>
  );
};

export const TenantBehaviorSettings = () => {
  const [configs, setConfigs] = useState<ConfigDto[]>([]);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const [data, tenantSettings] = await Promise.all([
        configService.getAll(),
        tenantSettingsService.getSettings(),
      ]);
      setConfigs(syncIgvConfig(data, tenantSettings.igv));
    } catch {
      toast('Error al cargar las configuraciones', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    queueMicrotask(() => void load());
  }, []);

  const handleSave = async (config: ConfigDto, newValue: string) => {
    if (config.name === ConfigKey.IGV) {
      const igv = Number(newValue);
      if (!Number.isFinite(igv)) {
        toast('El IGV debe ser un número válido', 'error');
        return;
      }
      const updatedSettings = await tenantSettingsService.patchSettings({ igv });
      setConfigs((prev) => syncIgvConfig(prev, updatedSettings.igv));
      settings.invalidate();
      toast('Configuración guardada', 'success');
      return;
    }

    const updated = await configService.patch(config.id, newValue);
    setConfigs((prev) => prev.map((c) => (c.id === updated.id ? updated : c)));
    settings.invalidate();
    toast('Configuración guardada', 'success');
  };

  const visible = configs.filter((c) => !isTechnical(c.name));
  const general = visible.filter((c) => getMeta(c.name).group === 'general');
  const sales = visible.filter((c) => getMeta(c.name).group === 'sales');
  const inventory = visible.filter((c) => getMeta(c.name).group === 'inventory');
  const other = visible.filter((c) => !CONFIG_META[c.name]);

  if (loading) {
    return (
      <div className="settings-loading">
        <i className="ph ph-circle-notch settings-loading__icon" aria-hidden="true" />
        Cargando configuraciones…
      </div>
    );
  }

  return (
    <div className="config-content">
      <SettingGroup title="General" configs={general} onSave={handleSave} />
      <SettingGroup title="Ventas" configs={sales} onSave={handleSave} />
      <SettingGroup title="Inventario" configs={inventory} onSave={handleSave} />
      {other.length > 0 && <SettingGroup title="Otras" configs={other} onSave={handleSave} />}
      {visible.length === 0 && <p className="config-empty">No hay configuraciones disponibles.</p>}
    </div>
  );
};
