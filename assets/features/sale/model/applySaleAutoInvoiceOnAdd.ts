import type { RefObject } from 'react';
import type { DxFormEvents } from '@platform/form/DxForm';
import { Setting, settings } from '@assets/Settings';

export const applySaleAutoInvoiceOnAdd = async (formRef: RefObject<DxFormEvents | null>) => {
  const autoInvoice = await settings.get(Setting.AUTO_INVOICE);
  if (autoInvoice) {
    formRef.current?.setFieldValue('emit_invoice', autoInvoice.value === 'true');
  }
};
