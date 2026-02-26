import { Field } from '@platform/field/Field';
import { FieldBuilder } from '@platform/field/FieldBuilder';
import { FieldType } from '@platform/field/FieldType';
import toast, { SharedUtils } from '@shared/lib/SharedUtils';
import { Constants } from '@platform/common/Constants';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import { textField } from '@platform/field/TextFieldBuilder';
import { DxFormEvents } from '@platform/form/DxForm';
import { consultService } from '@features/consult';

export const CompanyFields = (formRef: DxFormEvents | null) => {
  const fields: Field[] = [];

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .visible(false)
      .type(FieldType.NONE)
      .name('id')
      .label('Id')
      .required(true)
      .build(),
  );
  fields.push(
    textField()
      .name('ruc')
      .label('Ruc')
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
            validationCallback: () => {
              const doc = formRef?.getFieldValue('ruc');
              return SharedUtils.validateRUC(doc ?? '');
            },
            message: 'El RUC ingresado no es válido',
          },
        },
      ])
      .required(true)
      .buttons([
        {
          name: 'search',
          location: 'after',
          options: {
            icon: 'search',
            stylingMode: 'text',
            onClick: () => {
              const doc = formRef?.getFieldValue('ruc');
              if (!doc || doc.length !== 11) {
                toast('Ingresa un RUC válido de 11 dígitos', 'warning');
                return;
              }
              consultService.consultRuc(doc).subscribe({
                next: (response) => {
                  if (!response.data.active) {
                    toast('El RUC ingresado no se encuentra activo', 'error');
                    return;
                  }
                  formRef?.setFieldValue('businessName', response.data.name);
                  formRef?.setFieldValue('tradeName', response.data.name);
                  formRef?.setFieldValue('fiscalAddress', response.data.address);
                },
                error: () => toast('No se pudo consultar el RUC', 'error'),
              });
            },
          },
        },
      ])
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('businessName')
      .label('Razon social')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder().type(FieldType.TEXT).name('tradeName').label('Nombre comercial').build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('fiscalAddress')
      .label('Domicilio fiscal')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .url(`${Constants.API_BASE_URL}ubigeos`)
      .valueField('code')
      .textField('name')
      .name('ubigeo')
      .label('Ubigeo (INEI)')
      .searchExpr(['code', 'department', 'province', 'district'])
      .visible(false)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('ubigeoCode')
      .label('Ubigeo (INEI)')
      .hidden(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('department')
      .label('Departmento')
      .readonly(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('province')
      .label('Provincia')
      .readonly(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('district')
      .label('Distrito')
      .readonly(true)
      .build(),
  );
  fields.push(new FieldBuilder().type(FieldType.TEXT).name('phone').label('Telefono').build());
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENUM)
      .name('bankName')
      .label('Banco')
      .data([
        { value: 'BCP', text: 'BCP' },
        { value: 'BBVA', text: 'BBVA' },
        { value: 'INTERBANK', text: 'INTERBANK' },
        { value: 'SCOTIABANK', text: 'SCOTIABANK' },
      ])
      .build(),
  );
  fields.push(
    new FieldBuilder().type(FieldType.TEXT).name('bankAccount').label('Cuenta bancaria').build(),
  );
  fields.push(new FieldBuilder().type(FieldType.TEXT).name('bankCci').label('CCI').build());
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('detraccionAccount')
      .label('Cuenta de detracciones')
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.FILE)
      .name('logo')
      .label('Logo')
      .align('center')
      .url(Constants.API_BASE_URL + 'media')
      .accept('image/*')
      .formatter((e: ColumnCellTemplateData) => {
        return SharedUtils.imageFormatter(e.data?.logo?.path, e.data?.nombre_comercial);
      })
      .build(),
  );

  return fields;
};
