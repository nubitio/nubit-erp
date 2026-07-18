import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  FieldType,
  type DataRecord,
  type FormHandle,
  type GridCellContext,
} from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { customerApiUrl, customerEvents, customerFormLayout } from './model/constants';
import { consultService } from '@features/consult';
import toast, { SharedUtils } from '@shared/lib/SharedUtils';
import { useTranslation } from 'react-i18next';

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

type FieldButtonForm = Pick<FormHandle, 'getFieldValue' | 'setFieldValue'>;

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

const customerResource = defineResource<CustomerRecord>(customerApiUrl, {
  id: 'customer',
  title: 'Clientes',
  events: customerEvents,
  defaultPreset: 'basic',
  columnPresets: CUSTOMER_COLUMN_PRESETS,
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  formLayout: customerFormLayout,
  fieldContract: defineFields<CustomerRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'businessName', patch: { order: 1, layoutHint: 'fullWidth', label: 'Razón social' } },
      {
        kind: 'override',
        key: 'fiscalAddress',
        patch: { order: 2, type: FieldType.TEXTAREA, layoutHint: 'long', label: 'Domicilio fiscal' },
      },
      { kind: 'override', key: 'phone', patch: { order: 3, layoutHint: 'short', label: 'Teléfono' } },
      { kind: 'override', key: 'email', patch: { order: 4, layoutHint: 'short', label: 'Correo' } },
      {
        kind: 'override',
        key: 'isActive',
        patch: {
          order: 5,
          width: 100,
          layoutHint: 'short',
          label: 'Activo',
          type: FieldType.SWITCH,
          formatter: (cell: GridCellContext) => SharedUtils.booleanFormatter(Boolean(cell.value)),
        },
      },
      {
        kind: 'override',
        key: 'ruc',
        patch: {
          order: 0,
          layoutHint: 'identity',
          label: 'RUC/DNI',
          maxLength: 11,
          validators: [
            {
              type: 'stringLength',
              options: {
                min: 8,
                max: 11,
                message: 'El RUC/DNI debe tener entre 8 y 11 dígitos',
              },
            },
            {
              type: 'pattern',
              options: {
                pattern: '^[0-9]+$',
                message: 'El RUC/DNI solo debe contener números',
              },
            },
            {
              type: 'custom',
              options: {
                validationCallback: ({ value }) => {
                  const doc = String(value ?? '');
                  return doc.length === 8 ? true : SharedUtils.validateRUC(doc);
                },
                message: 'El RUC ingresado no es válido',
              },
            },
          ],
          buttons: [
            {
              name: 'search',
              location: 'after',
              options: {
                icon: 'search',
                stylingMode: 'text',
                onClick: (_event: unknown, form: FieldButtonForm) => {
                  const doc = String(form.getFieldValue('ruc') ?? '').trim();
                  if (doc.length < 8) return;

                  if (doc.length === 8) {
                    consultService
                      .consultDni(doc)
                      .then((response) => {
                        form.setFieldValue('businessName', response.data.name);
                      })
                      .catch(() => toast('No se pudo consultar el DNI', 'error'));
                  } else if (doc.length === 11) {
                    consultService
                      .consultRuc(doc)
                      .then((response) => {
                        if (!response.data.active) {
                          toast('El RUC ingresado no se encuentra activo', 'error');
                          return;
                        }
                        form.setFieldValue('businessName', response.data.name);
                        form.setFieldValue('fiscalAddress', response.data.address);
                      })
                      .catch(() => toast('No se pudo consultar el RUC', 'error'));
                  }
                },
              },
            },
          ],
        },
      },
      {
        kind: 'override',
        key: 'priceList',
        patch: {
          type: FieldType.ENTITY,
          url: API + 'price-lists',
          valueField: '_iri',
          textField: 'name',
          layoutHint: 'lookup',
          label: 'Tarifa',
        },
      },
    ],
  }),
});

export const CustomerPage = () => {
  const { t } = useTranslation();

  return <SchemaCrudPage resource={{ ...customerResource, title: t('customer.title') }} />;
};

// Static properties consumed by SalePage — must stay as module-level singletons.
CustomerPage.EVENTS = customerEvents;
CustomerPage.API_URL = customerApiUrl;
