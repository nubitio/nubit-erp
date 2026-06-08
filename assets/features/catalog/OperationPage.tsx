import {
  SmartCrudPage,
  defineFields,
  defineResource,
  enumField,
  type DataRecord,
  type Field,
  type SmartCrudFieldPatch,
} from '@nubit/crud';
import { Badge } from '@nubit/ui';
import React from 'react';
import { operationEndpoints } from './api/catalogEndpoints';

interface OperationRecord extends DataRecord {
  id: string;
  name: string;
  type: number;
}

function toPatch(field: Field): SmartCrudFieldPatch<OperationRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<OperationRecord>;
}

const typePatch = toPatch(
  enumField([
    { value: 1, text: 'Entrada' },
    { value: 2, text: 'Salida' },
  ])
    .name('type')
    .label('Tipo')
    .align('center')
    .formatter((cell) => {
      if (cell.value === 1) {
        return <Badge variant="success" outlined>Entrada</Badge>;
      }

      if (cell.value === 2) {
        return <Badge variant="danger" outlined>Salida</Badge>;
      }

      return null;
    })
    .required(true)
    .build(),
);

const resource = defineResource<OperationRecord>(operationEndpoints.list(), {
  id: 'operation',
  title: 'Operaciones',
  viewMode: 'dialog',
  emptyState: {
    title: 'Sin operaciones de inventario',
    description: 'Define tipos de entrada y salida para movimientos de stock.',
    icon: 'arrows-left-right',
  },
  fieldContract: defineFields<OperationRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'name', patch: { order: 0, width: 280, label: 'Nombre' } },
      { kind: 'override', key: 'type', patch: { ...typePatch, order: 1, width: 140 } },
    ],
  }),
});
export const OperationPage = () => <SmartCrudPage resource={resource} />;
