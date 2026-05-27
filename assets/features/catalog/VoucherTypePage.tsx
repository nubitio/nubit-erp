import { SmartCrudPage, defineResource, defineFieldContract } from '@nubit/crud';
import { voucherTypeEndpoints } from './api/catalogEndpoints';

const resource = defineResource(voucherTypeEndpoints.list(), {
  id: 'voucherType',
  title: 'Tipos de comprobante',
  fieldContract: defineFieldContract({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'id', patch: { isIdentity: false, visible: false, visibleOnForm: false } },
      { kind: 'override', key: 'code', patch: { isIdentity: true } },
    ],
  }),
});
export const VoucherTypePage = () => <SmartCrudPage resource={resource} />;
