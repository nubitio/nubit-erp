import {
  FieldType,
  SchemaCrudPage,
  defineFields,
  defineResource,
  type DataRecord,
} from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { customerSubscriptionApiUrl, customerSubscriptionLineApiUrl } from './model/constants';
import { hasPermission } from '@shared/permissions';

const API = getCoreApiBaseUrl();

interface SubscriptionLineRecord extends DataRecord {
  id: string;
  subscription: unknown;
  product: unknown;
  description: string | null;
  quantity: string;
  unitPrice: string;
  sortOrder: number;
}

const resource = defineResource<SubscriptionLineRecord>(customerSubscriptionLineApiUrl, {
  id: 'customer-subscription-lines',
  title: 'Líneas de suscripción',
  defaultPreset: 'basic',
  columnPresets: [
    {
      key: 'basic',
      label: 'Básico',
      columns: ['subscription', 'product', 'description', 'quantity', 'unitPrice', 'sortOrder'],
    },
  ],
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  emptyState: {
    title: 'Sin líneas personalizadas',
    description: 'Si no agregas líneas, se usa el producto y monto principal del plan.',
    icon: 'list-plus',
  },
  formLayout: {
    type: 'sections',
    sections: [
      { label: 'Suscripción', fields: ['subscription'] },
      {
        label: 'Concepto',
        fields: ['product', 'description', 'quantity', 'unitPrice', 'sortOrder'],
      },
    ],
  },
  fieldContract: defineFields<SubscriptionLineRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      {
        kind: 'override',
        key: 'subscription',
        patch: {
          order: 0,
          type: FieldType.ENTITY,
          url: customerSubscriptionApiUrl,
          valueField: '_iri',
          textField: 'serviceReference',
          label: 'Suscripción',
          layoutHint: 'lookup',
        },
      },
      {
        kind: 'override',
        key: 'product',
        patch: {
          order: 1,
          type: FieldType.ENTITY,
          url: API + 'products',
          valueField: '_iri',
          textField: 'name',
          label: 'Producto/servicio',
          layoutHint: 'lookup',
        },
      },
      {
        kind: 'override',
        key: 'description',
        patch: {
          order: 2,
          label: 'Descripción para comprobante',
          layoutHint: 'fullWidth',
          helpText: 'Vacío usa el nombre del producto.',
        },
      },
      {
        kind: 'override',
        key: 'quantity',
        patch: { order: 3, label: 'Cantidad', layoutHint: 'short' },
      },
      {
        kind: 'override',
        key: 'unitPrice',
        patch: { order: 4, label: 'Precio unitario', layoutHint: 'money' },
      },
      {
        kind: 'override',
        key: 'sortOrder',
        patch: { order: 5, label: 'Orden', layoutHint: 'short' },
      },
    ],
  }),
});

export const CustomerSubscriptionLinePage = () => (
  <SchemaCrudPage
    resource={{
      ...resource,
      permissions: {
        canAdd: hasPermission('subscriptions.edit'),
        canEdit: hasPermission('subscriptions.edit'),
        canDelete: hasPermission('subscriptions.edit'),
      },
    }}
  />
);

CustomerSubscriptionLinePage.API_URL = customerSubscriptionLineApiUrl;
