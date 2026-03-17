import { type RefObject } from 'react';
import { Constants } from '@core/common/Constants';
import { DxFormEvents } from '@core/form/DxForm';
import { Field } from '@core/field/Field';
import { entityField, textField, textareaField } from '@core/field/FieldBuilders';
import { consultService } from '@features/consult';
import { SharedUtils } from '@shared/lib/SharedUtils';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import toast from '@shared/lib/SharedUtils';

export const CustomerFields = (formRef: RefObject<DxFormEvents | null>) => {
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
            validationCallback: (e: any) => {
              const doc = e.value;
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
      .buttons([
        {
          name: 'search',
          location: 'after',
          options: {
            icon: 'search',
            stylingMode: 'text',
            onClick: (e: any) => {
              const doc: string = e.component.option('value');
              if (doc.length === 8) {
                consultService.consultDni(doc).subscribe((response) => {
                  formRef.current?.setFieldValue('businessName', response.data.name);
                });
              } else if (doc.length === 11) {
                consultService.consultRuc(doc).subscribe((response) => {
                  if (!response.data.active) {
                    toast('El RUC ingresado no se encuentra activo', 'error');
                    return;
                  }
                  formRef.current?.setFieldValue('businessName', response.data.name);
                  formRef.current?.setFieldValue('fiscalAddress', response.data.address);
                });
              }
            },
          },
        },
      ])
      .valueType('string')
      .build(),
  );
  fields.push(
    textareaField().name('businessName').label('Cliente').required(true).sortable(true).build(),
  );
  fields.push(textareaField().name('fiscalAddress').label('Domicilio').build());
  fields.push(
    textField()
      .name('phone')
      .label('Teléfono')
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
      .validators([
        {
          type: 'email',
          options: { message: 'El email ingresado no es válido' },
        },
      ])
      .build(),
  );
  fields.push(
    entityField(Constants.API_BASE_URL + 'price-lists', 'id', 'name')
      .name('priceList')
      .label('Tarifa')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .valueType('number')
      .build(),
  );

  return fields;
};
