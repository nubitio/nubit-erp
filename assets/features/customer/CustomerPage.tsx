import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  type DataRecord,
} from '@nubitio/crud';
import { customerApiUrl, customerEvents, customerFormLayout } from './model/constants';
import { CustomerFields } from './CustomerFields';
import { useTranslation } from 'react-i18next';
import { hasPermission } from '@shared/permissions';

interface CustomerRecord extends DataRecord {
  id: string;
  ruc: string;
  businessName: string;
  fiscalAddress: string;
  phone: string;
  email: string;
  priceList: unknown;
  isActive: boolean;
}

const CUSTOMER_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['ruc', 'businessName', 'phone', 'email', 'isActive'],
  },
  {
    key: 'full',
    label: 'Completo',
    columns: ['ruc', 'businessName', 'fiscalAddress', 'phone', 'email', 'priceList', 'isActive'],
  },
];

const customerFields = CustomerFields({ current: null });

const customerResource = defineResource<CustomerRecord>(customerApiUrl, {
  id: 'customer',
  title: 'Clientes',
  permissions: {
    canAdd: () => hasPermission('customers.create'),
    canEdit: () => hasPermission('customers.edit'),
    canDelete: () => hasPermission('customers.delete'),
    canExport: true,
  },
  events: customerEvents,
  defaultPreset: 'basic',
  columnPresets: CUSTOMER_COLUMN_PRESETS,
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  formLayout: customerFormLayout,
  emptyState: {
    title: 'Sin clientes',
    description: 'Crea un cliente o impórtalos desde Inicio → Importar Excel.',
    icon: 'users',
  },
  fieldContract: defineFields<CustomerRecord>({
    source: 'manual',
    strategy: 'replace',
    fields: customerFields,
  }),
});

export const CustomerPage = () => {
  const { t } = useTranslation();
  return <SchemaCrudPage resource={{ ...customerResource, title: t('customer.title') }} />;
};

// Static properties consumed by SalePage — must stay as module-level singletons.
CustomerPage.EVENTS = customerEvents;
CustomerPage.API_URL = customerApiUrl;
