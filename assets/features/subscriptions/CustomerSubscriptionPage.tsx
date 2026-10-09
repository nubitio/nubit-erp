import {
  SchemaCrudPage,
  dateField,
  defineFields,
  defineResource,
  enumField,
  FieldType,
  type DataRecord,
  type Field,
  type GridCellContext,
  type ResourceToolbarContext,
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
import { SharedUtils } from '@shared/lib/SharedUtils';
import { useTranslation } from 'react-i18next';
import { httpClient } from '@shared/api/HttpClient';
import toast from '@shared/lib/SharedUtils';
import { hasPermission } from '@shared/permissions';
import { subscriptionLineFields } from './model/subscriptionLineFields';

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
  serviceReference: string | null;
  customDescription: string | null;
  agreedAmount: string | null;
  endDate: string | null;
  lines?: unknown[];
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

const startDatePatch = toPatch(
  dateField()
    .name('startDate')
    .label('Fecha de inicio')
    .required(true)
    .formatter((cell: GridCellContext) =>
      SharedUtils.dateFormatter(cell.value as string | Date | null | undefined),
    )
    .valueType('date')
    .build(),
);

const nextBillingDatePatch = toPatch(
  dateField()
    .name('nextBillingDate')
    .label('Próximo cobro')
    .required(true)
    .formatter((cell: GridCellContext) =>
      SharedUtils.dateFormatter(cell.value as string | Date | null | undefined),
    )
    .valueType('date')
    .build(),
);

const CUSTOMER_SUBSCRIPTION_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['customer', 'plan', 'status', 'nextBillingDate'],
  },
];

const customerSubscriptionResource = defineResource<CustomerSubscriptionRecord>(
  customerSubscriptionApiUrl,
  {
    id: 'customer-subscription',
    title: 'Suscripciones',
    events: customerSubscriptionEvents,
    defaultPreset: 'basic',
    columnPresets: CUSTOMER_SUBSCRIPTION_COLUMN_PRESETS,
    viewMode: { mode: 'drawer', drawerSize: 'xl' },
    formLayout: customerSubscriptionFormLayout,
    formDetail: {
      propertyName: 'lines',
      url: customerSubscriptionApiUrl + '/{id}/lines',
      fields: subscriptionLineFields(),
      allowAdding: hasPermission('subscriptions.create') || hasPermission('subscriptions.edit'),
      allowUpdating: hasPermission('subscriptions.edit'),
      allowDeleting: hasPermission('subscriptions.edit'),
      required: false,
    },
    toolbar: ({ selectedRow }: ResourceToolbarContext<CustomerSubscriptionRecord>) => ({
      selection: hasPermission('subscriptions.generate')
        ? [
            {
              text: 'Generar cargo ahora',
              icon: 'ph-receipt',
              type: 'success',
              group: 'billing',
              groupLabel: 'Facturación',
              onClick: () => {
                const id = selectedRow?.id;
                if (!id) {
                  toast('Selecciona una suscripción', 'warning');
                  return;
                }

                httpClient
                  .post(customerSubscriptionApiUrl + '/' + id + '/generate', { id })
                  .then(() => toast('Cargo generado y enviado a Cuentas por cobrar', 'success'))
                  .catch((error: { response?: { data?: { detail?: string } } }) =>
                    toast(error.response?.data?.detail ?? 'No se pudo generar el cargo', 'error'),
                  );
              },
            },
          ]
        : [],
    }),
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
            disabledWhen: (formData) => Boolean(formData.id),
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
            disabledWhen: (formData) => Boolean(formData.id),
          },
        },
        {
          kind: 'override',
          key: 'serviceReference',
          patch: {
            order: 2,
            layoutHint: 'fullWidth',
            label: 'Referencia del servicio',
            helpText: 'Dominio, servidor, contrato, sede u otro identificador del servicio.',
          },
        },
        {
          kind: 'override',
          key: 'customDescription',
          patch: {
            order: 3,
            layoutHint: 'fullWidth',
            label: 'Descripción para el comprobante',
            helpText: 'Se combinará con la referencia y el período facturado.',
          },
        },
        {
          kind: 'override',
          key: 'agreedAmount',
          patch: {
            order: 4,
            layoutHint: 'money',
            label: 'Precio pactado',
            helpText: 'Vacío usa el monto definido en el plan.',
          },
        },
        {
          kind: 'override',
          key: 'status',
          patch: { ...statusPatch, order: 5, layoutHint: 'short' },
        },
        {
          kind: 'override',
          key: 'startDate',
          patch: { ...startDatePatch, order: 6, layoutHint: 'short' },
        },
        {
          kind: 'override',
          key: 'nextBillingDate',
          patch: { ...nextBillingDatePatch, order: 7, layoutHint: 'short' },
        },
        {
          kind: 'override',
          key: 'endDate',
          patch: {
            ...toPatch(
              dateField()
                .name('endDate')
                .label('Fecha de término')
                .formatter((cell: GridCellContext) =>
                  SharedUtils.dateFormatter(cell.value as string | Date | null | undefined),
                )
                .valueType('date')
                .build(),
            ),
            order: 8,
            layoutHint: 'short',
            helpText: 'Opcional. No se generarán períodos posteriores a esta fecha.',
          },
        },
        {
          kind: 'override',
          key: 'autoRenew',
          patch: {
            order: 9,
            layoutHint: 'short',
            label: 'Renovación automática',
            type: FieldType.SWITCH,
          },
        },
        { kind: 'remove', key: 'currentPeriodStart' },
        { kind: 'remove', key: 'currentPeriodEnd' },
      ],
    }),
  },
);

export const CustomerSubscriptionPage = () => {
  const { t } = useTranslation();
  const resource = {
    ...customerSubscriptionResource,
    permissions: {
      canAdd: hasPermission('subscriptions.create'),
      canEdit: hasPermission('subscriptions.edit'),
      canDelete: hasPermission('subscriptions.cancel'),
    },
  };

  return (
    <SchemaCrudPage
      resource={{ ...resource, title: t('customerSubscription.title', 'Suscripciones') }}
    />
  );
};

CustomerSubscriptionPage.EVENTS = customerSubscriptionEvents;
CustomerSubscriptionPage.API_URL = customerSubscriptionApiUrl;
