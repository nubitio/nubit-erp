export type DialogMode = 'add' | 'edit' | 'view';

export interface DialogState {
  isOpen: boolean;
  mode: DialogMode;
  rowData: Record<string, unknown> | null;
}

export interface DialogActions {
  openDialog: (mode: DialogMode, rowData?: Record<string, unknown> | null) => void;
  closeDialog: () => void;
}

export type DialogSlice = DialogState & DialogActions;

export type DialogStoreState = Record<string, DialogState>;

export type DialogAction =
  | { type: 'OPEN'; resourceId: string; mode: DialogMode; rowData: Record<string, unknown> | null }
  | { type: 'CLOSE'; resourceId: string };

export function initialDialogState(): DialogState {
  return { isOpen: false, mode: 'add', rowData: null };
}

export function dialogReducer(state: DialogStoreState, action: DialogAction): DialogStoreState {
  switch (action.type) {
    case 'OPEN':
      return {
        ...state,
        [action.resourceId]: {
          isOpen: true,
          mode: action.mode,
          rowData: action.rowData,
        },
      };
    case 'CLOSE': {
      const existing = state[action.resourceId] ?? initialDialogState();
      return {
        ...state,
        [action.resourceId]: {
          ...existing,
          isOpen: false,
        },
      };
    }
    default:
      return state;
  }
}
