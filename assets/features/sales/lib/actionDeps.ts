export type BaseActionDeps = {
  showLoading: (message?: string) => void;
  hideLoading: () => void;
  refreshGrid: () => void;
  notify: (message: string, type: 'success' | 'error' | 'warning' | 'info') => void;
};

export type ActionDeps<TRow> = BaseActionDeps & {
  getSelectedRow: () => TRow | undefined;
};
