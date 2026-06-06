import { SmartCrudPage, defineResource, defineFieldContract } from '@nubit/crud';
import { voucherTypeEndpoints } from './api/catalogEndpoints';

const resource = defineResource(voucherTypeEndpoints.list(), {
  id: 'voucherType',
  title: 'Tipos de comprobante',
  viewMode: { mode: 'drawer', drawerWidth: 560 },
  fieldContract: defineFieldContract({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'id', patch: { isIdentity: false, visible: false, visibleOnForm: false } },
      { kind: 'override', key: 'code', patch: { isIdentity: true, order: 0, width: 120, label: 'Código' } },
      { kind: 'override', key: 'name', patch: { order: 1, width: 280, label: 'Nombre' } },
    ],
  }),
});
export const VoucherTypePage = () => <SmartCrudPage resource={resource} />;
