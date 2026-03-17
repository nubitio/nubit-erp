import { type RefObject } from 'react';
import { Field } from '@core/field/Field';
import { identityField, textField, textareaField } from '@core/field/FieldBuilders';
import toast, { SharedUtils } from '@shared/lib/SharedUtils';
import { DxFormEvents } from '@core/form/DxForm';
import { consultService } from '@features/consult';

export const SupplierFields = (formRef: RefObject<DxFormEvents | null>) => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    textField()
      .name('ruc')
      .label('RUC')
      .maxLength(11)
      .validators([
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
            validationCallback: (e: any) => {
              const doc: string = e.value;
              if (doc.length === 8) {
                return true;
              } else {
                return SharedUtils.validateRUC(doc);
              }
            },
            message: 'El RUC/DNI ingresado no es válido',
          },
        },
      ])
      .buttons([
        {
          name: 'search',
          location: 'after',
          options: {
            icon: 'search',
            stylingMode: 'text',
            onClick: (e: any) => {
              const doc: string = e.component.option('value');
              consultService.consultRuc(doc).subscribe((response) => {
                if (!response.data.active) {
                  toast('El RUC ingresado no se encuentra activo', 'error');
                  return;
                }
                formRef.current?.setFieldValue('businessName', response.data.name);
                formRef.current?.setFieldValue('fiscalAddress', response.data.address);
              });
            },
          },
        },
      ])
      .required(true)
      .build(),
  );
  fields.push(textareaField().name('businessName').label('Razón Social').required(true).build());
  fields.push(textField().name('fiscalAddress').label('Dirección').build());
  fields.push(
    textField()
      .name('phone')
      .label('Teléfono')
      .maxLength(15)
      .validators([
        {
          type: 'pattern',
          options: {
            pattern: '^\\+?[1-9]\\d{1,2}[-.\\s]?\\d{6,14}$',
            message: 'El teléfono ingresado no es válido',
          },
        },
      ])
      .align('center')
      .build(),
  );

  return fields;
};
