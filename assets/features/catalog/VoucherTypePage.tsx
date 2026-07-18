import {
  SchemaCrudPage,
  defineResource,
  defineFieldContract,
  FieldType,
  type GridCellContext,
} from '@nubitio/crud';
import { voucherTypeEndpoints } from './api/catalogEndpoints';
import { SharedUtils } from '@shared/lib/SharedUtils';

const resource = defineResource(voucherTypeEndpoints.list(), {
  id: 'voucherType',
  title: 'Tipos de comprobante',
  viewMode: 'dialog',
  emptyState: {
    title: 'Sin tipos de comprobante',
    description: 'Registra los tipos de documento que emite tu empresa.',
    icon: 'receipt',
  },
  fieldContract: defineFieldContract({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'id', patch: { isIdentity: false, visible: false, visibleOnForm: false } },
      { kind: 'override', key: 'code', patch: { isIdentity: true, order: 0, width: 120, label: 'Código' } },
      { kind: 'override', key: 'name', patch: { order: 1, width: 280, label: 'Nombre' } },
      {
        kind: 'override',
        key: 'isActive',
        patch: {
          order: 2,
          width: 100,
          label: 'Activo',
          type: FieldType.SWITCH,
          formatter: (cell: GridCellContext) => SharedUtils.booleanFormatter(Boolean(cell.value)),
        },
      },
    ],
  }),
});
export const VoucherTypePage = () => <SchemaCrudPage resource={resource} />;
