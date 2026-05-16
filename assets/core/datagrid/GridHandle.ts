import type { DxFilterRule } from '../field/FilterRule';

export interface GridHandle {
  showLoading: (message?: string) => void;
  hideLoading: () => void;
  refresh: () => void;
  reset: () => void;
  loadData: () => Promise<unknown>;
  getSelectedRowKey: () => string | number | undefined;
  getSelectedRow: () => Record<string, unknown> | undefined;
  getSelectedRowKeys: () => unknown[];
  getSelectedRows: () => Record<string, unknown>[];
  getFilter: () => unknown[];
  filter: (filterRule: DxFilterRule | null) => void;
}
