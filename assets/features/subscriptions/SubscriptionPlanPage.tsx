import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  enumField,
  FieldType,
  type DataRecord,
  type Field,
  type GridCellContext,
  type SmartCrudFieldPatch,
} from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { subscriptionPlanApiUrl, subscriptionPlanEvents, subscriptionPlanFormLayout } from './model/constants';
import { SharedUtils } from '@shared/lib/SharedUtils';
import { useTranslation } from 'react-i18next';
import { moneyCell } from '@shared/ui/crudFormatters';
import { hasPermission } from '@shared/permissions';

interface SubscriptionPlanRecord extends DataRecord {
  id: string;
  name: string;
  product: unknown;
  billingCycle: string;
  amount: string;
  paymentTermDays: number | null;
  branch: unknown;
  warehouse: unknown;
  isActive: boolean;
}

function toPatch(field: Field): SmartCrudFieldPatch<SubscriptionPlanRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<SubscriptionPlanRecord>;
}

const billingCyclePatch = toPatch(
  enumField([
    { value: 'MONTHLY', text: 'Mensual' },
    { value: 'ANNUAL', text: 'Anual' },
  ])
    .name('billingCycle')
    .label('Ciclo de facturación')
    .required(true)
    .build(),
);

const SUBSCRIPTION_PLAN_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['name', 'product', 'billingCycle', 'amount', 'isActive'],
  },
];

const subscriptionPlanResource = defineResource<SubscriptionPlanRecord>(subscriptionPlanApiUrl, {
  id: 'subscription-plan',
  title: 'Planes de suscripción',
  events: subscriptionPlanEvents,
  defaultPreset: 'basic',
  columnPresets: SUBSCRIPTION_PLAN_COLUMN_PRESETS,
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  formLayout: subscriptionPlanFormLayout,
  emptyState: {
    title: 'Sin planes de suscripción',
    description: 'Crea un plan solo si facturas cuotas recurrentes. Si no, puedes ignorar este módulo.',
    icon: 'arrows-clockwise',
  },
  fieldContract: defineFields<SubscriptionPlanRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'name', patch: { order: 0, layoutHint: 'fullWidth', label: 'Nombre del plan' } },
      {
        kind: 'override',
        key: 'product',
        patch: {
          order: 1,
          type: FieldType.ENTITY,
          url: API + 'products',
          valueField: '_iri',
          textField: 'name',
          layoutHint: 'lookup',
          label: 'Producto/servicio',
        },
      },
      {
        kind: 'override',
        key: 'billingCycle',
        patch: { ...billingCyclePatch, order: 2, layoutHint: 'short' },
      },
      { kind: 'override', key: 'amount', patch: { order: 3, layoutHint: 'short', label: 'Monto por periodo', formatter: moneyCell, align: 'right' } },
      {
        kind: 'override',
        key: 'paymentTermDays',
        patch: {
          order: 4,
          layoutHint: 'short',
          label: 'Días de plazo de pago',
          helpText: 'Vacío usa el plazo por defecto del tenant.',
        },
      },
      {
        kind: 'override',
        key: 'branch',
        patch: {
          order: 5,
          type: FieldType.ENTITY,
          url: API + 'branches',
          valueField: '_iri',
          textField: 'name',
          layoutHint: 'lookup',
          label: 'Sucursal emisora',
          helpText: 'Sucursal a la que se atribuyen las ventas generadas por este plan.',
        },
      },
      {
        kind: 'override',
        key: 'warehouse',
        patch: {
          order: 6,
          type: FieldType.ENTITY,
          url: API + 'warehouses',
          valueField: '_iri',
          textField: 'name',
          layoutHint: 'lookup',
          label: 'Almacén emisor',
        },
      },
      {
        kind: 'override',
        key: 'isActive',
        patch: {
          order: 7,
          width: 100,
          layoutHint: 'short',
          label: 'Activo',
          type: FieldType.SWITCH,
          formatter: (cell: GridCellContext) => SharedUtils.booleanFormatter(Boolean(cell.value)),
        },
      },
    ],
  }),
});

export const SubscriptionPlanPage = () => {
  const { t } = useTranslation();
  const resource = {
    ...subscriptionPlanResource,
    permissions: {
      canAdd: hasPermission('subscriptions.create'),
      canEdit: hasPermission('subscriptions.edit'),
      canDelete: hasPermission('subscriptions.cancel'),
    },
  };

  return <SchemaCrudPage resource={{ ...resource, title: t('subscriptionPlan.title', 'Planes de suscripción') }} />;
};

SubscriptionPlanPage.EVENTS = subscriptionPlanEvents;
SubscriptionPlanPage.API_URL = subscriptionPlanApiUrl;
