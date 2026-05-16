import { SmartCrudPage, defineResource } from '@core/crud';
import { defineFields } from '@core/crud/fieldContract';
import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';
import { FieldType } from '@core/field/FieldType';
import { supplierEvents } from '@core/event/domainEvents';
import { supplierEndpoints } from '../api/catalogEndpoints';
import { consultService } from '@features/consult';
import toast, { SharedUtils } from '@shared/lib/SharedUtils';

interface SupplierRecord extends Record<string, unknown> {
  id: string;
  ruc: string;
  businessName: string;
  fiscalAddress: string;
  phone: string;
}

interface ButtonContext {
  formRef: MutableRefObject<FormRef | null>;
}

function setField(formRef: MutableRefObject<FormRef | null>, name: string, value: unknown) {
  formRef.current?.instance().getEditor(name)?.option('value', value);
}

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
                onClick: (_e: unknown, ctx: ButtonContext) => {
                  const formRef = ctx?.formRef;
                  const rawDoc = formRef?.current?.instance().getEditor('ruc')?.option('value');
                  const doc = String(rawDoc ?? '').trim();

                  if (!formRef?.current || doc.length !== 11 || !SharedUtils.validateRUC(doc)) {
                    toast('Ingresa un RUC válido de 11 dígitos', 'warning');
                    return;
                  }

                  consultService.consultRuc(doc).then((response) => {
                      if (!response.data.active) {
                        toast('El RUC ingresado no se encuentra activo', 'error');
                        return;
                      }
                      setField(formRef, 'businessName', response.data.name);
                      setField(formRef, 'fiscalAddress', response.data.address);
                    }).catch(() => toast('No se pudo consultar el RUC', 'error'));
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
