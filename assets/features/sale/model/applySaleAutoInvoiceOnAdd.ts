import type { RefObject } from 'react';
import type { FormHandle } from '@core/form/FormHandle';
import { Setting, settings } from '@app/Settings';

export const applySaleAutoInvoiceOnAdd = async (formRef: RefObject<FormHandle | null>) => {
  const autoInvoice = await settings.get(Setting.AUTO_INVOICE);
  if (autoInvoice) {
    formRef.current?.setFieldValue('emit_invoice', autoInvoice.value === 'true');
  }
};
