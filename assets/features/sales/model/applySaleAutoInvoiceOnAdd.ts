import type { RefObject } from 'react';
import type { FormHandle } from '@nubitio/crud';
import { Setting, settings } from '@shared/settings';

export const applySaleAutoInvoiceOnAdd = async (formRef: RefObject<FormHandle | null>) => {
  const autoInvoice = await settings.get(Setting.AUTO_INVOICE);
  if (autoInvoice) {
    formRef.current?.setFieldValue('emitInvoice', autoInvoice.value === 'true');
  }
};
