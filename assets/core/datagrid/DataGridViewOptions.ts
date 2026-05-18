import type { ReactNode } from 'react';
import type { DataGridEventNames, ToolbarButtonItem } from '@core/event/EventTypes';
import type { DataRecord } from '@core/data/DataRecord';
import type { Field } from '@core/field/Field';
import type { FilterRule } from '@core/field/FilterRule';
import type { GridHandle } from './GridHandle';

export interface DataGridSelectionChangedEvent {
  selectedRowsData?: DataRecord[];
}

export interface DataGridSummaryItem {
  column?: string;
  summaryType?: string;
  displayFormat?: string;
  valueFormat?: string;
  customizeText?: (cellInfo: unknown) => string;
}

export interface DataGridViewOptions {
  id: string;
  title: string;
  url: string;
  detailUrl?: string;
  fields: Field[];
  detailFields?: Field[] | ((parentRow: DataRecord) => Field[]);
  toolbar?: ToolbarButtonItem[] | ToolbarButtonItem[][];
  events?: DataGridEventNames;
  allowAdd?: boolean;
  allowEdit?: boolean;
  allowDelete?: boolean;
  allowExport?: boolean;
  addDisabled?: boolean;
  editDisabled?: boolean;
  deleteDisabled?: boolean;
  summaryFields?: DataGridSummaryItem[];
  filter?: FilterRule[];
  sort?: Array<{ selector: string; desc: boolean }>;
  mode?: 'minimal' | 'normal' | 'full';
  paging?: boolean;
  hasBackButton?: boolean;
  columnsHidingEnabled?: boolean;
  stateStoringEnabled?: boolean;
  toolbarVisible?: boolean;
  selectionMode?: 'single' | 'multiple';
  onBack?: () => void;
  onSelectionChanged?: (event: DataGridSelectionChangedEvent) => void;
  onRowPrepared?: (event: unknown) => void;
  onContentReady?: () => void;
  onFilterChange?: (filters: Record<string, string>) => void;
  filterRow?: boolean;
  headerFilter?: boolean;
  manualLoad?: boolean;
  beforeToolbar?: () => ReactNode;
  editMode?: 'popup' | 'row' | 'cell' | 'batch';
  visibleColumns?: string[] | null;
}

/** @deprecated Prefer GridHandle. */
export type DataGridViewEvents = GridHandle;
