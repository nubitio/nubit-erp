import { type RefObject } from 'react';
import { Field } from '@core/field/Field';
import {
  identityField,
  entityField,
  imageField,
  enumField,
  textField,
  textareaField,
} from '@core/field/FieldBuilders';
import { DxFormEvents } from '@core/form/DxForm';
import { consultService } from '@features/consult';
import toast, { SharedUtils } from '@shared/lib/SharedUtils';
import { Constants } from '@core/common/Constants';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const CompanyFields = (formRef: RefObject<DxFormEvents | null>) => {
  const fields: Field[] = [];

  fields.push(identityField().build());
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
            validationCallback: (e: any) => {
              const doc = e.value;
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
              const rawDoc = formRef.current?.getFieldValue('ruc');
              const doc = String(rawDoc ?? '').trim();
              if (!doc || doc.length !== 11 || !SharedUtils.validateRUC(doc)) {
                toast('Ingresa un RUC válido de 11 dígitos', 'warning');
                return;
              }
              consultService.consultRuc(doc).then((response) => {
                  if (!response.data.active) {
                    toast('El RUC ingresado no se encuentra activo', 'error');
                    return;
                  }
                  formRef.current?.setFieldValue('businessName', response.data.name);
                  formRef.current?.setFieldValue('tradeName', response.data.name);
                  formRef.current?.setFieldValue('fiscalAddress', response.data.address);
                }).catch(() => toast('No se pudo consultar el RUC', 'error'));
            },
          },
        },
      ])
      .build(),
  );
  fields.push(textField().name('businessName').label('Razon social').required(true).build());
  fields.push(textField().name('tradeName').label('Nombre comercial').build());
  fields.push(textareaField().name('fiscalAddress').label('Domicilio fiscal').required(true).build());
  fields.push(
    entityField(`${Constants.API_BASE_URL}ubigeos`, 'code', 'name')
      .name('ubigeo')
      .label('Ubigeo (INEI)')
      .searchExpr(['code', 'department', 'province', 'district'])
      .visible(false)
      .build(),
  );
  fields.push(textField().name('ubigeoCode').label('Ubigeo (INEI)').hidden(true).build());
  fields.push(textField().name('department').label('Departmento').readonly(true).build());
  fields.push(textField().name('province').label('Provincia').readonly(true).build());
  fields.push(textField().name('district').label('Distrito').readonly(true).build());
  fields.push(textField().name('phone').label('Telefono').build());
  fields.push(
    enumField([
      { value: 'BCP', text: 'BCP' },
      { value: 'BBVA', text: 'BBVA' },
      { value: 'INTERBANK', text: 'INTERBANK' },
      { value: 'SCOTIABANK', text: 'SCOTIABANK' },
    ])
      .name('bankName')
      .label('Banco')
      .build(),
  );
  fields.push(textField().name('bankAccount').label('Cuenta bancaria').build());
  fields.push(textField().name('bankCci').label('CCI').build());
  fields.push(textField().name('detraccionAccount').label('Cuenta de detracciones').build());
  fields.push(
    imageField(Constants.API_BASE_URL)
      .name('logo')
      .label('Logo')
      .formatter((e: ColumnCellTemplateData) => {
        return SharedUtils.imageFormatter(e.data?.logo?.path, e.data?.nombre_comercial);
      })
      .build(),
  );

  return fields;
};
