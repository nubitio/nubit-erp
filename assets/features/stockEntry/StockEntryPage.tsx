import { SmartCrudPage, defineResource } from '@nubit/crud';
import { API_BASE_URL } from '@app/constants';
import { createCrudEvents } from '@nubit/crud';
import type { StockEntryMode } from './StockEntryFields';
import { StockEntryFields, StockEntryItemFields } from './StockEntryFields';
import { useSessionInfo } from '@app/contexts/useSessionInfo';

const API_URL = API_BASE_URL + 'stock-entries';

const TITLES: Record<StockEntryMode, string> = {
  receipt:    'Entradas manuales',
  issue:      'Salidas manuales',
  transfer:   'Transferencias entre almacenes',
  adjustment: 'Ajustes de stock',
};

const TYPE_FILTER: Record<StockEntryMode, string> = {
  receipt:    'RECEIPT',
  issue:      'ISSUE',
  transfer:   'TRANSFER',
  adjustment: 'ADJUSTMENT',
};

interface StockEntryPageProps {
  mode: StockEntryMode;
}

export const StockEntryPage = ({ mode }: StockEntryPageProps) => {
  const { currentBranchId } = useSessionInfo();
  const events = createCrudEvents(`stock-entry-${mode}`);
  const detailFields = StockEntryItemFields(mode);
  const detailUrl = API_URL + '/{id}/items';

  const resource = defineResource({
    id:        `stock-entry-${mode}`,
    title:     TITLES[mode],
    apiUrl:    `${API_URL}?type=${TYPE_FILTER[mode]}`,
    fields:    StockEntryFields(mode, currentBranchId),
    gridDetail: { url: detailUrl, fields: detailFields },
    formDetail: { url: detailUrl, fields: detailFields },
    events,
    sort:      [{ selector: 'createdAt', desc: true }],
    permissions: {
      canAdd:    true,
      canEdit:   false,  // stock entries are immutable
      canDelete: false,
    },
  });

  return <SmartCrudPage resource={resource} />;
};

// ── Type-specific page exports (one component per route) ──────────────────────

export const ReceiptPage    = () => <StockEntryPage mode="receipt" />;
export const IssuePage      = () => <StockEntryPage mode="issue" />;
export const TransferPage   = () => <StockEntryPage mode="transfer" />;
export const AdjustmentPage = () => <StockEntryPage mode="adjustment" />;
