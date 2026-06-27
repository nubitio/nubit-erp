import { SchemaCrudPage, defineResource } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { createCrudEvents } from '@nubitio/crud';
import type { StockEntryMode } from './StockEntryFields';
import { StockEntryFields, StockEntryItemFields } from './StockEntryFields';
import { useSessionInfo } from '@shared/sessionInfo';
import { useTenantRuntimeConfig } from '@shared/hooks';
import { useNavigate, useLocation } from 'react-router-dom';

const API_URL = API + 'stock-entries';

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
  const { data: runtimeConfig, isLoading } = useTenantRuntimeConfig();
  const navigate = useNavigate();
  const location = useLocation();
  const returnTo = new URLSearchParams(location.search).get('returnTo');
  const events = createCrudEvents(`stock-entry-${mode}`);
  const detailFields = StockEntryItemFields(mode);
  const detailUrl = API_URL + '/{id}/items';
  const showWarehouseSelector = runtimeConfig?.ui.inventory.showWarehouseSelector ?? true;
  const basicColumns = showWarehouseSelector ? ['createdAt', 'warehouse', 'typeLabel'] : ['createdAt', 'typeLabel'];
  const fullColumns = showWarehouseSelector
    ? ['createdAt', 'warehouse', 'targetWarehouse', 'operation', 'typeLabel', 'note']
    : ['createdAt', 'operation', 'typeLabel', 'note'];

  if (mode === 'transfer' && runtimeConfig?.ui.inventory.showTransfers === false) {
    return (
      <div style={{ padding: 24 }}>
        Las transferencias entre almacenes no están habilitadas para este tenant.
      </div>
    );
  }

  if (isLoading && !runtimeConfig) {
    return <div style={{ padding: 24 }}>Cargando configuración...</div>;
  }

  const resource = defineResource(`${API_URL}?type=${TYPE_FILTER[mode]}`, {
    id:     `stock-entry-${mode}`,
    title:  TITLES[mode],
    fields: StockEntryFields(mode, currentBranchId, runtimeConfig),
    gridDetail: { url: detailUrl, fields: detailFields },
    formDetail: { url: detailUrl, fields: detailFields },
    events,
    sort:      [{ selector: 'createdAt', desc: true }],
    viewMode: 'page',
    routing: { routeParam: 'id' },
    defaultPreset: 'basic',
    columnPresets: [
      {
        key: 'basic',
        label: 'Básico',
        columns: basicColumns,
      },
      {
        key: 'full',
        label: 'Completo',
        columns: fullColumns,
      },
    ],
    permissions: {
      canAdd:    true,
      canEdit:   false,  // stock entries are immutable
      canDelete: false,
    },
    mercure: false,
    onSaveSuccess: () => {
      if (returnTo) {
        navigate(returnTo);
      }
    },
  });

  return <SchemaCrudPage resource={resource} />;
};

// ── Type-specific page exports (one component per route) ──────────────────────

export const ReceiptPage    = () => <StockEntryPage mode="receipt" />;
export const IssuePage      = () => <StockEntryPage mode="issue" />;
export const TransferPage   = () => <StockEntryPage mode="transfer" />;
export const AdjustmentPage = () => <StockEntryPage mode="adjustment" />;
