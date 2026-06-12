import {
  FieldType,
  SmartCrudPage,
  defineFields,
  defineResource,
  type DataRecord,
  type FormHandle,
} from '@nubitio/crud';
import { supplierEndpoints } from './api/catalogEndpoints';
import { consultService } from '@features/consult';
import toast, { SharedUtils } from '@shared/lib/SharedUtils';

interface SupplierRecord extends DataRecord {
  id: string;
  ruc: string;
  businessName: string;
  fiscalAddress: string;
  phone: string;
}

type FieldButtonForm = Pick<FormHandle, 'getFieldValue' | 'setFieldValue'>;

const SUPPLIER_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['ruc', 'businessName', 'phone'],
  },
  {
    key: 'full',
    label: 'Completo',
    columns: ['ruc', 'businessName', 'fiscalAddress', 'phone'],
  },
];

const resource = defineResource<SupplierRecord>(supplierEndpoints.list(), {
  id: 'supplier',
  title: 'Proveedores',
  defaultPreset: 'basic',
  columnPresets: SUPPLIER_COLUMN_PRESETS,
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  auditTrail: {
    enabled: true,
    apiUrl: (id) => `/api/audit-trail/supplier/${id}`,
  },
  formLayout: {
    type: 'sections',
    sections: [
      {
        label: 'Identificación',
        fields: ['ruc', 'businessName', 'fiscalAddress'],
      },
      {
        label: 'Contacto',
        fields: ['phone'],
      },
    ],
  },
  fieldContract: defineFields<SupplierRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'businessName', patch: { order: 1, layoutHint: 'fullWidth', label: 'Razón social' } },
      { kind: 'override', key: 'phone', patch: { order: 3, layoutHint: 'short', label: 'Teléfono' } },
      {
        kind: 'override',
        key: 'ruc',
        patch: {
          order: 0,
          layoutHint: 'identity',
          label: 'RUC',
          maxLength: 11,
          validators: [
            {
              type: 'stringLength',
              options: { min: 11, max: 11, message: 'El RUC debe tener 11 dígitos' },
            },
            {
              type: 'pattern',
              options: { pattern: '^[0-9]+$', message: 'El RUC solo debe contener números' },
            },
            {
              type: 'custom',
              options: {
                validationCallback: (value) => {
                  const doc = String(value ?? '').trim();
                  return SharedUtils.validateRUC(doc);
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

                  if (doc.length !== 11 || !SharedUtils.validateRUC(doc)) {
                    toast('Ingresa un RUC válido de 11 dígitos', 'warning');
                    return;
                  }

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
                },
              },
            },
          ],
        },
      },
      {
        kind: 'override',
        key: 'fiscalAddress',
        patch: { order: 2, type: FieldType.TEXTAREA, layoutHint: 'long', label: 'Domicilio fiscal' },
      },
    ],
  }),
});

export const SupplierPage = () => <SmartCrudPage resource={resource} />;
