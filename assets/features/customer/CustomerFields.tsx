import { type RefObject } from 'react';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import type { FormHandle, GridCellContext } from '@nubitio/crud';
import { Field } from '@nubitio/crud';
import { entityField, switchField, textField, textareaField } from '@nubitio/crud';
import { SharedUtils } from '@shared/lib/SharedUtils';
import { lookupCustomerDocument } from './model/lookupCustomerDocument';

type ValidationEvent = {
  value?: unknown;
};

type FieldButtonForm = Pick<FormHandle, 'getFieldValue' | 'setFieldValue'> & {
  isCurrent?: () => boolean;
};

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
      .layoutHint('fullWidth')
      .helpText('Ingresa 8 dígitos para DNI u 11 para RUC. Puedes consultar sus datos.')
      .buttons([
        {
          name: 'search',
          location: 'after',
          options: {
            icon: 'search',
            text: 'Consultar',
            ariaLabel: 'Consultar RUC o DNI',
            stylingMode: 'text',
            loadingText: 'Consultando…',
            onClick: (_event: unknown, form: FieldButtonForm) =>
              lookupCustomerDocument(form ?? formRef.current),
          },
        },
      ])
      .valueType('string')
      .build(),
  );
  fields.push(
    textField()
      .name('businessName')
      .label('Razón social')
      .layoutHint('fullWidth')
      .required(true)
      .sortable(true)
      .build(),
  );
  fields.push(
    textareaField().name('fiscalAddress').label('Dirección fiscal').layoutHint('long').build(),
  );
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
      .label('Correo')
      .helpText('Correo de contacto del cliente.')
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
      .label('Cliente activo')
      .defaultValue(true)
      .helpText('Disponible para seleccionarlo en ventas.')
      .layoutHint('short')
      .formatter((cell: GridCellContext) => SharedUtils.booleanFormatter(Boolean(cell.value)))
      .build(),
  );

  return fields;
};
