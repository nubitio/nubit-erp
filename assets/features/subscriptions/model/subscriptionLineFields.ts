import { entityField, identityField, numberField, textField, type Field } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

export function subscriptionLineFields(): Field[] {
  return [
    identityField().build(),
    entityField(API + 'products', 'id', 'name')
      .name('product')
      .label('Producto o servicio')
      .required(true)
      .build(),
    textField().name('description').label('Descripción para comprobante').build(),
    numberField().name('quantity').label('Cantidad').required(true).defaultValue(1).build(),
    numberField().name('unitPrice').label('Precio unitario').required(true).defaultValue(0).build(),
    numberField().name('sortOrder').label('Orden').defaultValue(0).build(),
  ];
}
