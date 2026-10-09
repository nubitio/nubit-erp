import { type RefObject } from 'react';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import type { FormHandle, GridCellContext } from '@nubitio/crud';
import { Field } from '@nubitio/crud';
import { entityField, switchField, textField, textareaField } from '@nubitio/crud';
import { consultService } from '@features/consult';
import toast, { SharedUtils } from '@shared/lib/SharedUtils';

type ValidationEvent = {
  value?: unknown;
};

type FieldButtonForm = Pick<FormHandle, 'getFieldValue' | 'setFieldValue'>;

export const CustomerFields = (formRef: RefObject<FormHandle | null>) => {
  const fields: Field[] = [];

  fields.push(
    textField()
      .isIdentity(true)
      .name('id')
      .label('ID')
      .visible(false)
      .visibleOnForm(false)
      .filterable(false)
      .sortable(false)
      .build(),
  );
  fields.push(
    textField()
      .name('ruc')
      .label('RUC/DNI')
      .maxLength(11)
      .validators([
        {
          type: 'stringLength',
          options: { min: 8, max: 11, message: 'El RUC/DNI debe tener entre 8 y 11 dígitos' },
        },
        {
          type: 'pattern',
          options: { pattern: '^[0-9]+$', message: 'El RUC/DNI solo debe contener números' },
        },
        {
          type: 'custom',
          options: {
            validationCallback: (e: ValidationEvent) => {
              const doc = String(e.value ?? '');
              if (doc.length === 8) {
                return true;
              } else {
                return SharedUtils.validateRUC(doc);
              }
            },
            message: 'El RUC ingresado no es válido',
          },
        },
      ])
      .required(true)
      .sortable(true)
      .layoutHint('identity')
      .buttons([
        {
          name: 'search',
          location: 'after',
          options: {
            icon: 'search',
            stylingMode: 'text',
            onClick: (_event: unknown, form: FieldButtonForm) => {
              const activeForm = form ?? formRef.current;
              const doc = String(activeForm?.getFieldValue('ruc') ?? '').trim();
              if (doc.length === 8) {
                consultService
                  .consultDni(doc)
                  .then((response) => {
                    activeForm?.setFieldValue('businessName', response.data.name);
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
                    activeForm?.setFieldValue('businessName', response.data.name);
                    activeForm?.setFieldValue('fiscalAddress', response.data.address);
                  })
                  .catch(() => toast('No se pudo consultar el RUC', 'error'));
              }
            },
          },
        },
      ])
      .valueType('string')
      .build(),
  );
  fields.push(
    textareaField()
      .name('businessName')
      .label('Razón social')
      .layoutHint('fullWidth')
      .required(true)
      .sortable(true)
      .build(),
  );
  fields.push(textareaField().name('fiscalAddress').label('Domicilio').layoutHint('long').build());
  fields.push(
    textField()
      .name('phone')
      .label('Teléfono')
      .layoutHint('short')
      .validators([
        {
          type: 'pattern',
          options: {
            pattern: '^\\+?[1-9]\\d{1,2}[-.\\s]?\\d{6,14}$',
            message: 'El teléfono ingresado no es válido',
          },
        },
      ])
      .build(),
  );
  fields.push(
    textField()
      .name('email')
      .label('Email')
      .layoutHint('short')
      .validators([
        {
          type: 'email',
          options: { message: 'El email ingresado no es válido' },
        },
      ])
      .build(),
  );
  fields.push(
    entityField(API + 'price-lists', 'id', 'name')
      .name('priceList')
      .label('Tarifa')
      .layoutHint('lookup')
      .formatter((cell: GridCellContext) => (cell.value as { name?: string } | undefined)?.name)
      .valueType('number')
      .build(),
  );
  fields.push(
    switchField()
      .name('isActive')
      .label('Activo')
      .layoutHint('short')
      .formatter((cell: GridCellContext) => SharedUtils.booleanFormatter(Boolean(cell.value)))
      .build(),
  );

  return fields;
};
