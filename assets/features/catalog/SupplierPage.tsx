import {
  FieldType,
  SmartCrudPage,
  defineFields,
  defineResource,
  type DataRecord,
  type FormHandle,
} from '@nubit/crud';
import { supplierEvents } from '@app/domainEvents';
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

const resource = defineResource<SupplierRecord>({
  id: 'supplier',
  title: 'Proveedores',
  apiUrl: supplierEndpoints.list(),
  fields: [],
  events: supplierEvents,
  fieldContract: defineFields<SupplierRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      {
        kind: 'override',
        key: 'ruc',
        patch: {
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
                validationCallback: (e: { value: string }) => {
                  const doc = String(e.value ?? '').trim();
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
        patch: { type: FieldType.TEXTAREA },
      },
    ],
  }),
});

export const SupplierPage = () => <SmartCrudPage resource={resource} />;
