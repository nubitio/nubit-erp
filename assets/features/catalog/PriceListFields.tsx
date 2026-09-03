import { identityField, numberField, textField, type Field } from '@nubitio/crud';

export const PriceListFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().valueType('number').build());
  fields.push(textField().name('name').label('Nombre').required(true).build());
  fields.push(
    numberField()
      .name('multiplier')
      .label('Factor general de precios')
      .helpText('Se aplica automáticamente a todos los productos que no tengan un precio especial. Usa 0.90 para cobrar 10% menos, 1.00 para mantener el precio normal o 1.10 para cobrar 10% más.')
      .precision(4)
      .validators([{ type: 'range', options: { min: 0, max: 99.9999 } }])
      .build(),
  );

  return fields;
};
