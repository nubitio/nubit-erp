type FormRecord = Record<string, unknown>;

export interface FormHandle {
  getFormData: () => FormRecord | undefined;
  getFieldValue: (name: string) => unknown;
  setFieldValue: (name: string, value: unknown) => void;
  getDetailData: () => FormRecord[] | undefined;
  setDetailData: (data: FormRecord[]) => void;
  saveDetailData: () => Promise<void> | void;
  reloadDetailData: () => void;
  setValues: (data: FormRecord) => void;
  setReadonly: (field: string, value: boolean) => void;
  setDisabled: (field: string, value: boolean) => void;
  enableValidation: (field: string) => void;
  disableValidation: (field: string) => void;
  setError: (field: string, message: string) => void;
  showField: (field: string) => void;
  hideField: (field: string) => void;
  validate: () => boolean;
  setIsEdit: (value: boolean) => void;
}
