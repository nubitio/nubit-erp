import { identityField, numberField, textField, type Field } from '@nubitio/crud';

export const PriceListFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().valueType('number').build());
  fields.push(textField().name('name').label('Nombre').required(true).build());
  fields.push(
    numberField()
      .name('multiplier')
      .label('Multiplicador')
      .helpText('Opcional. Si se define, el precio efectivo es precio_base × multiplicador cuando no hay precio individual. Ej: 0.90 = 10% de descuento, 1.10 = 10% de recargo.')
      .format({ min: 0, max: 99.9999, step: 0.01, decimalPlaces: 4 })
      .build(),
  );

  return fields;
};
