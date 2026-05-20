import { CrudPage, defineResource } from '@nubit/core';
import { Constants } from '@core/common/Constants';
import { createCrudEvents } from '@core/event/createCrudEvents';
import type { StockEntryMode } from './StockEntryFields';
import { StockEntryFields, StockEntryItemFields } from './StockEntryFields';

const API_URL = Constants.API_BASE_URL + 'stock-entries';

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
  const events = createCrudEvents(`stock-entry-${mode}`);

  const resource = defineResource({
    id:        `stock-entry-${mode}`,
    title:     TITLES[mode],
    apiUrl:    `${API_URL}?type=${TYPE_FILTER[mode]}`,
    fields:    StockEntryFields(mode),
    detailFields: StockEntryItemFields(mode),
    detailUrl: API_URL + '/{id}/items',
    events,
    sort:      [{ selector: 'createdAt', desc: true }],
    permissions: {
      canAdd:    true,
      canEdit:   false,  // stock entries are immutable
      canDelete: false,
    },
  });

  return <CrudPage resource={resource} />;
};

// ── Type-specific page exports (one component per route) ──────────────────────

export const ReceiptPage    = () => <StockEntryPage mode="receipt" />;
export const IssuePage      = () => <StockEntryPage mode="issue" />;
export const TransferPage   = () => <StockEntryPage mode="transfer" />;
export const AdjustmentPage = () => <StockEntryPage mode="adjustment" />;
