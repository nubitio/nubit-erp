import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  enumField,
  FieldType,
  type DataRecord,
  type Field,
  type SmartCrudFieldPatch,
} from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import {
  customerSubscriptionApiUrl,
  customerSubscriptionEvents,
  customerSubscriptionFormLayout,
} from './model/constants';
import { subscriptionPlanApiUrl } from './model/constants';
import { useTranslation } from 'react-i18next';

interface CustomerSubscriptionRecord extends DataRecord {
  id: string;
  customer: unknown;
  plan: unknown;
  status: string;
  startDate: string;
  currentPeriodStart: string | null;
  currentPeriodEnd: string | null;
  nextBillingDate: string;
  autoRenew: boolean;
}

function toPatch(field: Field): SmartCrudFieldPatch<CustomerSubscriptionRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<CustomerSubscriptionRecord>;
}

const statusPatch = toPatch(
  enumField([
    { value: 'active', text: 'Activa' },
    { value: 'paused', text: 'Pausada' },
    { value: 'cancelled', text: 'Cancelada' },
  ])
    .name('status')
    .label('Estado')
    .required(true)
    .build(),
);

const CUSTOMER_SUBSCRIPTION_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['customer', 'plan', 'status', 'nextBillingDate'],
  },
];

const customerSubscriptionResource = defineResource<CustomerSubscriptionRecord>(customerSubscriptionApiUrl, {
  id: 'customer-subscription',
  title: 'Suscripciones',
  events: customerSubscriptionEvents,
  defaultPreset: 'basic',
  columnPresets: CUSTOMER_SUBSCRIPTION_COLUMN_PRESETS,
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  formLayout: customerSubscriptionFormLayout,
  fieldContract: defineFields<CustomerSubscriptionRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      {
        kind: 'override',
        key: 'customer',
        patch: {
          order: 0,
          type: FieldType.ENTITY,
          url: API + 'customers',
          valueField: '_iri',
          textField: 'businessName',
          layoutHint: 'lookup',
          label: 'Cliente',
        },
      },
      {
        kind: 'override',
        key: 'plan',
        patch: {
          order: 1,
          type: FieldType.ENTITY,
          url: subscriptionPlanApiUrl,
          valueField: '_iri',
          textField: 'name',
          layoutHint: 'lookup',
          label: 'Plan',
        },
      },
      {
        kind: 'override',
        key: 'status',
        patch: { ...statusPatch, order: 2, layoutHint: 'short' },
      },
      { kind: 'override', key: 'startDate', patch: { order: 3, layoutHint: 'short', label: 'Fecha de inicio' } },
      {
        kind: 'override',
        key: 'nextBillingDate',
        patch: { order: 4, layoutHint: 'short', label: 'Próximo cobro' },
      },
      {
        kind: 'override',
        key: 'autoRenew',
        patch: { order: 5, layoutHint: 'short', label: 'Renovación automática', type: FieldType.SWITCH },
      },
    ],
  }),
});

export const CustomerSubscriptionPage = () => {
  const { t } = useTranslation();

  return (
    <SchemaCrudPage
      resource={{ ...customerSubscriptionResource, title: t('customerSubscription.title', 'Suscripciones') }}
    />
  );
};

CustomerSubscriptionPage.EVENTS = customerSubscriptionEvents;
CustomerSubscriptionPage.API_URL = customerSubscriptionApiUrl;
