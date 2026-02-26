import { Constants } from '@platform/common/Constants';
import { DxFormEvents } from '@platform/form/DxForm';
import { Field } from '@platform/field/Field';
import { FieldBuilder } from '@platform/field/FieldBuilder';
import { textField } from '@platform/field/TextFieldBuilder';
import { FieldType } from '@platform/field/FieldType';
import { consultService } from '@features/consult';
import { SharedUtils } from '@shared/lib/SharedUtils';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import notify from 'devextreme/ui/notify';

export const CustomerFields = (formRef: DxFormEvents | null) => {
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
            validationCallback: (_e: any) => {
              const doc = formRef?.getFieldValue('ruc');
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
            onClick: (_e: any) => {
              const doc = formRef?.getFieldValue('ruc');
              if (doc.length === 8) {
                consultService.consultDni(doc).subscribe((response) => {
                  formRef?.setFieldValue('businessName', response.data.name);
                });
              } else if (doc.length === 11) {
                consultService.consultRuc(doc).subscribe((response) => {
                  if (!response.data.active) {
                    notify(
                      {
                        message: 'El RUC ingresado no se encuentra activo',
                        type: 'error',
                        displayTime: 3000,
                      },
                      { position: 'top right' },
                    );
                    return;
                  }
                  formRef?.setFieldValue('businessName', response.data.name);
                  formRef?.setFieldValue('fiscalAddress', response.data.address);
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
    new FieldBuilder()
      .type(FieldType.TEXTAREA)
      .name('businessName')
      .label('Cliente')
      .required(true)
      .sortable(true)
      .build(),
  );
  fields.push(
    new FieldBuilder().type(FieldType.TEXTAREA).name('fiscalAddress').label('Domicilio').build(),
  );
  fields.push(
    new FieldBuilder()
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
    new FieldBuilder()
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
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('priceList')
      .label('Tarifa')
      .url(Constants.API_BASE_URL + 'price-lists')
      .valueField('id')
      .textField('name')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .valueType('number')
      .build(),
  );

  return fields;
};
