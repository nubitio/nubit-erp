import { useState } from 'react';
import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  enumField,
  FieldType,
  type DataRecord,
  type Field,
  type GridCellContext,
  type SmartCrudFieldPatch,
} from '@nubitio/crud';
import { dispatch, getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { Badge, ConfirmDialog } from '@nubitio/ui';
import { hasPermission } from '@shared/permissions';
import toast from '@shared/lib/SharedUtils';
import { dateCell, moneyCell } from '@shared/ui/crudFormatters';
import { fixedAssetApiUrl, fixedAssetEvents, fixedAssetFormLayout } from './model/constants';
import fixedAssetService from './services/fixedAssetService';

interface FixedAssetRecord extends DataRecord {
  id: string;
  description: string;
  purchaseDate: string;
  cost: string;
  usefulLifeMonths: number;
  sourceBill: unknown;
  accumulatedDepreciation: string;
  status: 'ACTIVE' | 'FULLY_DEPRECIATED' | 'DISPOSED';
  note: string | null;
}

function toPatch(field: Field): SmartCrudFieldPatch<FixedAssetRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<FixedAssetRecord>;
}

const STATUS_BADGE: Record<FixedAssetRecord['status'], { variant: 'success' | 'warning' | 'secondary' | 'danger'; text: string }> = {
  ACTIVE: { variant: 'success', text: 'Activo' },
  FULLY_DEPRECIATED: { variant: 'secondary', text: 'Depreciado' },
  DISPOSED: { variant: 'danger', text: 'Dado de baja' },
};

const statusPatch = toPatch(
  enumField([
    { value: 'ACTIVE', text: 'Activo' },
    { value: 'FULLY_DEPRECIATED', text: 'Depreciado' },
    { value: 'DISPOSED', text: 'Dado de baja' },
  ])
    .name('status')
    .label('Estado')
    .formatter((cell: GridCellContext) => {
      const badge = STATUS_BADGE[cell.value as FixedAssetRecord['status']];
      if (!badge) return null;

      return <Badge variant={badge.variant} outlined>{badge.text}</Badge>;
    })
    .build(),
);

const FIXED_ASSET_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['description', 'purchaseDate', 'cost', 'accumulatedDepreciation', 'status'],
  },
];

const fixedAssetResource = defineResource<FixedAssetRecord>(fixedAssetApiUrl, {
  id: 'fixed-asset',
  title: 'Activos fijos',
  events: fixedAssetEvents,
  defaultPreset: 'basic',
  columnPresets: FIXED_ASSET_COLUMN_PRESETS,
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  formLayout: fixedAssetFormLayout,
  sort: [{ selector: 'purchaseDate', desc: true }],
  emptyState: {
    title: 'Sin activos fijos',
    description: 'Registra un activo para empezar a depreciarlo.',
    icon: 'buildings',
  },
  fieldContract: defineFields<FixedAssetRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'description', patch: { order: 0, layoutHint: 'fullWidth', label: 'Descripción' } },
      { kind: 'override', key: 'purchaseDate', patch: { order: 1, layoutHint: 'short', label: 'Fecha de compra', formatter: dateCell } },
      {
        kind: 'override',
        key: 'sourceBill',
        patch: {
          order: 2,
          type: FieldType.ENTITY,
          url: API + 'supplier-bills',
          valueField: '_iri',
          textField: 'number',
          layoutHint: 'lookup',
          label: 'Factura de origen',
        },
      },
      { kind: 'override', key: 'cost', patch: { order: 3, layoutHint: 'short', label: 'Costo', formatter: moneyCell, align: 'right' } },
      { kind: 'override', key: 'usefulLifeMonths', patch: { order: 4, layoutHint: 'short', label: 'Vida útil (meses)' } },
      { kind: 'override', key: 'accumulatedDepreciation', patch: { order: 5, layoutHint: 'short', label: 'Depreciación acumulada', disabled: true, visibleOnForm: false, formatter: moneyCell } },
      { kind: 'override', key: 'status', patch: { ...statusPatch, order: 6, layoutHint: 'short', disabled: true, visibleOnForm: false } },
      { kind: 'override', key: 'note', patch: { order: 7, layoutHint: 'fullWidth', label: 'Notas' } },
      { kind: 'override', key: 'disposedAt', patch: { visibleOnForm: false } },
      { kind: 'override', key: 'disposalNote', patch: { visibleOnForm: false } },
      { kind: 'override', key: 'createdAt', patch: { visibleOnForm: false, visible: false } },
    ],
  }),
});

export const FixedAssetPage = () => {
  const [confirmDispose, setConfirmDispose] = useState<{ id: string } | null>(null);

  const runDispose = () => {
    if (!confirmDispose) return;
    const { id } = confirmDispose;

    fixedAssetService()
      .dispose(id)
      .then(() => {
        toast('Activo dado de baja', 'success');
        setConfirmDispose(null);
        dispatch(fixedAssetEvents.SUCCESS);
      })
      .catch(() => {
        toast('No se pudo dar de baja el activo', 'error');
        setConfirmDispose(null);
      });
  };

  const resource = {
    ...fixedAssetResource,
    rowActions: (row: FixedAssetRecord) => {
      const actions = [];

      if (row.status !== 'DISPOSED' && hasPermission('fixed_assets.dispose')) {
        actions.push({
          key: 'dispose',
          text: 'Dar de baja',
          icon: 'ph-trash',
          type: 'danger' as const,
          onClick: () => setConfirmDispose({ id: row.id }),
        });
      }

      return actions;
    },
  };

  return (
    <>
      <SchemaCrudPage resource={resource} />
      <ConfirmDialog
        open={confirmDispose !== null}
        title="Dar de baja activo"
        message="¿Confirmas que este activo debe darse de baja? Ya no acumulará depreciación."
        variant="danger"
        onConfirm={runDispose}
        onCancel={() => setConfirmDispose(null)}
      />
    </>
  );
};

FixedAssetPage.EVENTS = fixedAssetEvents;
FixedAssetPage.API_URL = fixedAssetApiUrl;
