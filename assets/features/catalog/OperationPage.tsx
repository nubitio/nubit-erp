import {
  SmartCrudPage,
  defineFields,
  defineResource,
  enumField,
  type DataRecord,
  type Field,
  type SmartCrudFieldPatch,
} from '@nubit/core';
import React from 'react';
import { operationEvents } from '@core/event/domainEvents';
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
        return <span className="badge badge-outlined rounded-pill text-bg-success">Entrada</span>;
      }

      if (cell.value === 2) {
        return <span className="badge badge-outlined rounded-pill text-bg-danger">Salida</span>;
      }

      return null;
    })
    .required(true)
    .build(),
);

const resource = defineResource<OperationRecord>({
  id: 'operation',
  title: 'Operaciones',
  apiUrl: operationEndpoints.list(),
  fields: [],
  events: operationEvents,
  fieldContract: defineFields<OperationRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'name', patch: { width: '100%' } },
      { kind: 'override', key: 'type', patch: typePatch },
    ],
  }),
});
export const OperationPage = () => <SmartCrudPage resource={resource} />;
