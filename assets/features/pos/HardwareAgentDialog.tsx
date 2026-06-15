import { useCallback, useEffect, useState } from 'react';
import { AppDialog, AppDropdown, Button, TextField, Toggle } from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import {
  fetchAgentConfig,
  fetchAgentHealth,
  fetchAgentPrinters,
  fetchScalePorts,
  fetchScaleStatus,
  fetchScaleWeight,
  isLocalAgentAvailable,
  LocalAgentNotAvailableError,
  saveAgentConfig,
  type AgentConfig,
  type ScaleWeightReading,
  type SerialPortInfo,
} from '@features/sales/lib/localAgentClient';
import './HardwareAgentDialog.scss';

type TabId = 'status' | 'printer' | 'scale';

interface HardwareAgentDialogProps {
  open: boolean;
  onClose: () => void;
}

const INSTALL_CMD =
  'curl -fsSL https://raw.githubusercontent.com/nubitio/efact-hardware-agent/main/install.sh | bash';

export function HardwareAgentDialog({ open, onClose }: HardwareAgentDialogProps) {
  const [tab, setTab] = useState<TabId>('status');
  const [loading, setLoading] = useState(false);
  const [agentOnline, setAgentOnline] = useState(false);
  const [version, setVersion] = useState<string | null>(null);
  const [configPath, setConfigPath] = useState('');
  const [config, setConfig] = useState<AgentConfig | null>(null);
  const [printers, setPrinters] = useState<string[]>([]);
  const [ports, setPorts] = useState<SerialPortInfo[]>([]);
  const [scaleStatus, setScaleStatus] = useState<string>('');
  const [liveWeight, setLiveWeight] = useState<ScaleWeightReading | null>(null);
  const [saving, setSaving] = useState(false);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const online = await isLocalAgentAvailable();
      setAgentOnline(online);
      if (!online) {
        setConfig(null);
        setLiveWeight(null);
        return;
      }

      const [health, cfg, printerList, portList] = await Promise.all([
        fetchAgentHealth(),
        fetchAgentConfig(),
        fetchAgentPrinters(),
        fetchScalePorts(),
      ]);

      setVersion(health.version);
      setConfigPath(health.config_path ?? cfg.path);
      setConfig(cfg.config);
      setPrinters(printerList);
      setPorts(portList);

      try {
        const status = await fetchScaleStatus();
        setScaleStatus(
          status.enabled
            ? status.connected
              ? `Conectada${status.port ? ` (${status.port})` : ''}`
              : `Sin conexión${status.last_error ? `: ${status.last_error}` : ''}`
            : 'Desactivada',
        );
        if (status.enabled) {
          setLiveWeight(await fetchScaleWeight());
        } else {
          setLiveWeight(null);
        }
      } catch {
        setScaleStatus('No disponible');
        setLiveWeight(null);
      }
    } catch (err) {
      if (err instanceof LocalAgentNotAvailableError) {
        setAgentOnline(false);
      } else {
        toast(err instanceof Error ? err.message : 'Error al cargar agente', 'error');
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!open) return;
    void refresh();
  }, [open, refresh]);

  useEffect(() => {
    if (!open || !agentOnline || !config?.scale.enabled) return;

    const timer = window.setInterval(() => {
      fetchScaleWeight()
        .then(setLiveWeight)
        .catch(() => undefined);
    }, 500);

    return () => window.clearInterval(timer);
  }, [open, agentOnline, config?.scale.enabled]);

  const updatePrinter = (patch: Partial<AgentConfig['printer']>) => {
    setConfig((prev) =>
      prev ? { ...prev, printer: { ...prev.printer, ...patch } } : prev,
    );
  };

  const updateScale = (patch: Partial<AgentConfig['scale']>) => {
    setConfig((prev) => (prev ? { ...prev, scale: { ...prev.scale, ...patch } } : prev));
  };

  const persistConfig = async (
    next: AgentConfig,
    successMessage?: string,
  ): Promise<boolean> => {
    setSaving(true);
    try {
      const result = await saveAgentConfig({
        printer: next.printer,
        scale: { ...next.scale, protocol: 'generic' },
      });
      setConfig(result.config);
      setConfigPath(result.path);
      if (successMessage) toast(successMessage, 'success');
      await refresh();
      return true;
    } catch (err) {
      toast(err instanceof Error ? err.message : 'No se pudo guardar', 'error');
      return false;
    } finally {
      setSaving(false);
    }
  };

  const handlePrinterToggle = async (checked: boolean) => {
    if (!config) return;
    const previous = config;
    const next: AgentConfig = {
      ...config,
      printer: { ...config.printer, prefer_system_backend: checked },
    };
    setConfig(next);
    const ok = await persistConfig(next);
    if (!ok) setConfig(previous);
  };

  const handleScaleToggle = async (checked: boolean) => {
    if (!config) return;
    const previous = config;
    const next: AgentConfig = {
      ...config,
      scale: { ...config.scale, enabled: checked },
    };
    setConfig(next);
    const ok = await persistConfig(next);
    if (!ok) setConfig(previous);
  };

  const handleSave = async () => {
    if (!config) return;
    await persistConfig(config, 'Configuración guardada');
  };

  const tabs: { id: TabId; label: string; icon: string }[] = [
    { id: 'status', label: 'Estado', icon: 'ph-heartbeat' },
    { id: 'printer', label: 'Impresora', icon: 'ph-printer' },
    { id: 'scale', label: 'Balanza', icon: 'ph-scales' },
  ];

  return (
    <AppDialog
      open={open}
      title="Hardware local"
      onClose={onClose}
      className="hardware-agent-dialog"
    >
      <div className="hardware-agent-dialog__tabs" role="tablist">
        {tabs.map((item) => (
          <button
            key={item.id}
            type="button"
            role="tab"
            aria-selected={tab === item.id}
            className={`hardware-agent-dialog__tab${tab === item.id ? ' is-active' : ''}`}
            onClick={() => setTab(item.id)}
          >
            <i className={item.icon} aria-hidden="true" />
            {item.label}
          </button>
        ))}
      </div>

      {loading && <p className="hardware-agent-dialog__hint">Cargando…</p>}

      {!loading && !agentOnline && tab === 'status' && (
        <div className="hardware-agent-dialog__offline">
          <p>
            El agente <strong>efact-hardware-agent</strong> no está corriendo en esta computadora.
          </p>
          <p>Instálalo una vez en la caja (macOS / Linux):</p>
          <pre className="hardware-agent-dialog__cmd">{INSTALL_CMD}</pre>
          <p className="hardware-agent-dialog__hint">
            También puedes abrir la configuración desde el icono del agente en la bandeja del
            sistema (menú → Abrir configuración).
          </p>
          <Button variant="secondary" onClick={() => void refresh()}>
            Reintentar conexión
          </Button>
        </div>
      )}

      {!loading && !agentOnline && tab !== 'status' && (
        <p className="hardware-agent-dialog__hint">
          Conecta el agente local para configurar esta sección. Ve a <strong>Estado</strong> para
          instalarlo.
        </p>
      )}

      {!loading && agentOnline && config && (
        <div className="hardware-agent-dialog__body">
          {tab === 'status' && (
            <div className="hardware-agent-dialog__panel">
              <dl className="hardware-agent-dialog__kv">
                <dt>Agente</dt>
                <dd>
                  <span className="hardware-agent-dialog__badge is-ok">En línea</span> v{version}
                </dd>
                <dt>URL</dt>
                <dd>
                  <code>http://127.0.0.1:8765</code>
                </dd>
                <dt>Configuración</dt>
                <dd>
                  <code>{configPath}</code>
                  <p className="hardware-agent-dialog__hint">
                    Referencia técnica (inglés):{' '}
                    <code>config.toml.example</code> en la misma carpeta — no se sobrescribe al
                    guardar.
                  </p>
                </dd>
                <dt>Balanza</dt>
                <dd>{scaleStatus}</dd>
              </dl>
              <Button variant="secondary" onClick={() => void refresh()}>
                Actualizar estado
              </Button>
            </div>
          )}

          {tab === 'printer' && (
            <div className="hardware-agent-dialog__panel">
              <Toggle
                label="Preferir impresora del sistema (spooler)"
                checked={Boolean(config.printer.prefer_system_backend)}
                onChange={(checked) => void handlePrinterToggle(checked)}
                disabled={saving}
                aria-label="Preferir impresora del sistema"
              />
              <label className="hardware-agent-dialog__field">
                <span>Nombre de impresora del sistema (opcional)</span>
                <TextField
                  value={config.printer.system_printer_name ?? ''}
                  onChange={(e) =>
                    updatePrinter({
                      system_printer_name: e.target.value || null,
                    })
                  }
                  placeholder="Ej. POS_D_BASIC_230"
                />
              </label>
              {printers.length > 0 && (
                <div className="hardware-agent-dialog__list">
                  <span className="hardware-agent-dialog__list-title">Detectadas</span>
                  <ul>
                    {printers.map((name) => (
                      <li key={name}>
                        <button
                          type="button"
                          className="hardware-agent-dialog__pick"
                          onClick={() => updatePrinter({ system_printer_name: name })}
                        >
                          {name}
                        </button>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          )}

          {tab === 'scale' && (
            <div className="hardware-agent-dialog__panel">
              <Toggle
                label="Activar balanza RS-232"
                checked={config.scale.enabled}
                onChange={(checked) => void handleScaleToggle(checked)}
                disabled={saving}
                aria-label="Activar balanza RS-232"
              />

              <div className="hardware-agent-dialog__row">
                <AppDropdown
                  id="scale-port"
                  label="Puerto serie"
                  value={config.scale.serial_port ?? ''}
                  options={[
                    { value: '', label: 'Seleccionar puerto…' },
                    ...ports.map((port) => ({
                      value: port.name,
                      label: port.description
                        ? `${port.name} — ${port.description}`
                        : port.name,
                    })),
                  ]}
                  onChange={(value) => updateScale({ serial_port: value || null })}
                />
                <Button variant="secondary" onClick={() => void fetchScalePorts().then(setPorts)}>
                  Actualizar
                </Button>
              </div>

              {config.scale.enabled && (
                <div className="hardware-agent-dialog__live-weight">
                  <span className="hardware-agent-dialog__live-label">Peso en vivo</span>
                  <strong>
                    {liveWeight?.kg != null ? `${liveWeight.kg.toFixed(3)} kg` : '—'}
                  </strong>
                  {liveWeight?.stable && (
                    <span className="hardware-agent-dialog__badge is-ok">Estable</span>
                  )}
                </div>
              )}
            </div>
          )}

          {tab !== 'status' && (
            <div className="hardware-agent-dialog__footer">
              <Button variant="primary" onClick={() => void handleSave()} disabled={saving}>
                {saving ? 'Guardando…' : 'Guardar configuración'}
              </Button>
            </div>
          )}
        </div>
      )}
    </AppDialog>
  );
}