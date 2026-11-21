import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Alert, Skeleton } from '@nubitio/ui';
import {
  ConfigKey,
  configService,
  isTechnical,
  tenantSettingsService,
  type ConfigDto,
  type TenantSettingsDto,
} from '@features/config';
import { TENANT_RUNTIME_CONFIG_QUERY_KEY } from '@shared/hooks';
import { settings } from '@shared/settings';
import toast from '@shared/lib/SharedUtils';
import { SelectRow, ToggleRow, ValueRow, type ChoiceOption } from '../settings/SettingsControls';
import { SettingsGroup } from '../settings/SettingsRow';

const TENANT_SETTINGS_KEY = ['tenant-settings'] as const;
const CONFIG_VALUES_KEY = ['settings', 'config-values'] as const;

interface ConfigMeta {
  label: string;
  description?: string;
  group: 'general' | 'sales' | 'inventory';
  options?: ChoiceOption[];
  /** Largest value a numeric setting takes. */
  max?: number;
}

const CONFIG_META: Record<string, ConfigMeta> = {
  app_option_igv: {
    label: 'IGV (%)',
    description: 'Porcentaje de IGV aplicado a las ventas.',
    group: 'general',
    max: 100,
  },
  app_option_rc: {
    label: 'Recargo al consumo (%)',
    description: 'Porcentaje de recargo al consumo (RC) impreso en el comprobante.',
    group: 'general',
    max: 100,
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
      {
        value: 'WEBUSB',
        label: 'WebUSB',
        description: 'Imprime desde el navegador, sin instalar nada.',
      },
      {
        value: 'LOCAL_AGENT',
        label: 'Agente de hardware',
        description: 'Impresora y balanza conectadas a este equipo.',
      },
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
    description: 'Define qué ocurre cuando una venta dejaría el stock por debajo de cero.',
    group: 'inventory',
    options: [
      { value: 'STRICT', label: 'Bloquear la venta' },
      { value: 'WARNING_ONLY', label: 'Permitir mostrando una advertencia' },
      { value: 'ALLOW_NEGATIVE', label: 'Permitir sin advertencia' },
    ],
  },
};

const TAX_REGIMES: ChoiceOption[] = [
  { value: 'GENERAL', label: 'Régimen General' },
  { value: 'NRUS', label: 'Nuevo RUS' },
];

const metaOf = (name: string): ConfigMeta => CONFIG_META[name] ?? { label: name, group: 'general' };

/** The IGV lives on the typed tenant settings; the generic config list only mirrors it. */
function syncIgvConfig(configs: ConfigDto[], igv: number): ConfigDto[] {
  const value = String(igv);
  if (!configs.some((config) => config.name === ConfigKey.IGV)) {
    return [...configs, { id: -1, name: ConfigKey.IGV, value, type: 'float' }];
  }
  return configs.map((config) =>
    config.name === ConfigKey.IGV ? { ...config, value, type: 'float' } : config,
  );
}

function useTenantSettings() {
  const queryClient = useQueryClient();
  const query = useQuery({
    queryKey: TENANT_SETTINGS_KEY,
    queryFn: () => tenantSettingsService.getSettings(),
  });
  const patch = useMutation({
    mutationFn: (change: Partial<TenantSettingsDto>) => tenantSettingsService.patchSettings(change),
    onSuccess: (updated) => {
      queryClient.setQueryData(TENANT_SETTINGS_KEY, updated);
      settings.invalidate();
      void queryClient.invalidateQueries({ queryKey: [...TENANT_RUNTIME_CONFIG_QUERY_KEY] });
      toast('Configuración guardada', 'success');
    },
    onError: () => toast('No se pudo guardar la configuración', 'error'),
  });
  return { query, patch };
}

/**
 * "Operación": how the point of sale, the receipts, taxes and stock behave. Every control saves
 * on its own, so there is no "Guardar" for the page.
 */
export const OperationSection = () => (
  <>
    <PointOfSaleGroup />
    <ReceiptsGroup />
    <BusinessRules />
  </>
);

/** The defaults each new sale starts with; the cashier can still change them per sale. */
const PointOfSaleGroup = () => {
  const { query, patch } = useTenantSettings();
  if (query.isError) return <Alert tone="warning">No se pudo cargar la configuración.</Alert>;
  if (query.isPending) return <Skeleton variant="rect" height={220} />;

  const state = query.data;
  const busy = patch.isPending;

  return (
    <SettingsGroup title="Punto de venta">
      <SelectRow
        id="operation-tax-regime"
        label="Régimen tributario"
        description={
          state.taxRegime === 'NRUS'
            ? 'Nuevo RUS: el negocio solo puede emitir boletas, nunca facturas.'
            : 'Régimen General: el negocio puede emitir boletas y facturas.'
        }
        value={state.taxRegime}
        options={TAX_REGIMES}
        disabled={busy}
        onChange={(value) => patch.mutate({ taxRegime: value as TenantSettingsDto['taxRegime'] })}
      />
      <ToggleRow
        label="Emitir boleta/factura por defecto"
        description={
          state.autoInvoice
            ? 'Cada venta nueva arranca con el comprobante electrónico activado. El cajero puede desactivarlo por venta.'
            : 'Las ventas nuevas arrancan sin comprobante. El cajero puede activarlo por venta.'
        }
        checked={state.autoInvoice}
        disabled={busy}
        onChange={(checked) => patch.mutate({ autoInvoice: checked })}
      />
      <ToggleRow
        label="Impresión automática"
        description={
          state.autoPrint
            ? 'El ticket se envía a la impresora automáticamente al cerrar cada venta.'
            : 'La venta se registra sin imprimir. Usa el botón "Imprimir" del panel de última venta.'
        }
        checked={state.autoPrint}
        disabled={busy}
        onChange={(checked) => patch.mutate({ autoPrint: checked })}
      />
      <ToggleRow
        label="Redondeo de efectivo a S/ 0.10"
        description={
          state.cashRoundingEnabled
            ? 'Las ventas 100% en efectivo se redondean hacia abajo a favor del cliente. Tarjeta, crédito y pagos mixtos mantienen céntimos exactos.'
            : 'Las ventas conservan céntimos exactos. Actívalo solo si este negocio aplica redondeo en efectivo.'
        }
        checked={state.cashRoundingEnabled}
        disabled={busy}
        onChange={(checked) => patch.mutate({ cashRoundingEnabled: checked })}
      />
      <ToggleRow
        label="Contar unidades en productos por peso"
        description={
          state.posPieceCountEnabled
            ? 'Las líneas vendidas por kg muestran un contador de unidades. El número se antepone a la descripción del comprobante (ej. "2 Pechuga de pollo").'
            : 'Útil en carnicerías y pollerías que venden por kg pero necesitan registrar cuántas piezas lleva el cliente.'
        }
        checked={state.posPieceCountEnabled ?? false}
        disabled={busy}
        onChange={(checked) => patch.mutate({ posPieceCountEnabled: checked })}
      />
    </SettingsGroup>
  );
};

/** `printLogo` is on the typed settings, not in the generic config list, so it has its own row. */
const ReceiptsGroup = () => {
  const { query, patch } = useTenantSettings();
  if (!query.data) return null;

  return (
    <SettingsGroup title="Comprobantes">
      <ToggleRow
        label="Imprimir logo en comprobantes"
        description={
          query.data.printLogo
            ? 'El logo de la empresa aparece en el ticket y en el PDF A4.'
            : 'Los comprobantes se emiten sin logo, solo con los datos de la empresa.'
        }
        checked={query.data.printLogo}
        disabled={patch.isPending}
        onChange={(checked) => patch.mutate({ printLogo: checked })}
      />
    </SettingsGroup>
  );
};

/** The generic key/value settings: taxes, currency, stock policy and whatever else the tenant has. */
const BusinessRules = () => {
  const queryClient = useQueryClient();
  const configs = useQuery({
    queryKey: CONFIG_VALUES_KEY,
    queryFn: async () => {
      const [all, tenant] = await Promise.all([
        configService.getAll(),
        tenantSettingsService.getSettings(),
      ]);
      return syncIgvConfig(all, tenant.igv);
    },
  });

  const replace = (update: (current: ConfigDto[]) => ConfigDto[]) =>
    queryClient.setQueryData<ConfigDto[]>(CONFIG_VALUES_KEY, (current) => update(current ?? []));

  const save = async (config: ConfigDto, value: string) => {
    if (config.name === ConfigKey.IGV) {
      const updated = await tenantSettingsService.patchSettings({ igv: Number(value) });
      queryClient.setQueryData(TENANT_SETTINGS_KEY, updated);
      replace((current) => syncIgvConfig(current, updated.igv));
    } else {
      const updated = await configService.patch(config.id, value);
      replace((current) => current.map((item) => (item.id === updated.id ? updated : item)));
    }
    settings.invalidate();
    toast('Configuración guardada', 'success');
  };

  if (configs.isError) return <Alert tone="warning">No se pudieron cargar los ajustes.</Alert>;
  if (configs.isPending) return <Skeleton variant="rect" height={220} />;

  // PRINT_LOGO has its own switch above; a legacy `config` row must not show it twice.
  const visible = configs.data.filter(
    (config) => !isTechnical(config.name) && config.name !== ConfigKey.PRINT_LOGO,
  );
  const section = (title: string, items: ConfigDto[]) =>
    items.length > 0 && (
      <SettingsGroup key={title} title={title}>
        {items.map((config) => (
          <ConfigRow key={config.id} config={config} onSave={save} />
        ))}
      </SettingsGroup>
    );

  const known = (group: ConfigMeta['group']) =>
    visible.filter((config) => config.name in CONFIG_META && metaOf(config.name).group === group);
  const other = visible.filter((config) => !(config.name in CONFIG_META));

  if (visible.length === 0) {
    return <p className="settings-muted">No hay más configuraciones disponibles.</p>;
  }

  return (
    <>
      {section('General', known('general'))}
      {section('Ventas', known('sales'))}
      {section('Inventario', known('inventory'))}
      {section('Otras', other)}
    </>
  );
};

const ConfigRow = ({
  config,
  onSave,
}: {
  config: ConfigDto;
  onSave: (config: ConfigDto, value: string) => Promise<void>;
}) => {
  const meta = metaOf(config.name);
  const id = `config-${config.name}`;
  const isBool = config.type === 'bool' || config.value === 'true' || config.value === 'false';
  const isNumber = config.type === 'int' || config.type === 'float';

  if (meta.options) {
    return (
      <SelectRow
        id={id}
        label={meta.label}
        description={meta.description}
        value={config.value}
        options={meta.options}
        onChange={(value) => void onSave(config, value).catch(() => undefined)}
      />
    );
  }

  if (isBool) {
    return (
      <ToggleRow
        label={meta.label}
        description={meta.description}
        checked={config.value === 'true'}
        onChange={(checked) => void onSave(config, String(checked)).catch(() => undefined)}
      />
    );
  }

  return (
    <ValueRow
      key={`${config.id}:${config.value}`}
      id={id}
      label={meta.label}
      description={meta.description}
      value={config.value}
      type={isNumber ? 'number' : 'text'}
      min={isNumber ? 0 : undefined}
      max={meta.max}
      step={config.type === 'float' ? 'any' : undefined}
      validate={(value) => validateConfig(value, isNumber, meta)}
      onSave={(value) => onSave(config, value)}
    />
  );
};

function validateConfig(value: string, isNumber: boolean, meta: ConfigMeta): string | null {
  if (!isNumber) return value === '' ? 'Indica un valor.' : null;
  const number = Number(value);
  if (value === '' || !Number.isFinite(number)) return 'Ingresa un número válido.';
  if (number < 0) return 'El valor no puede ser negativo.';
  if (meta.max !== undefined && number > meta.max) return `El valor no puede superar ${meta.max}.`;
  return null;
}
