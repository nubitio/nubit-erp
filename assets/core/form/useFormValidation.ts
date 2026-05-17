import toast from '@shared/lib/SharedUtils';
import type { Field } from '@core/field/Field';

import { validateDetailRows } from './FormDetailValidation';

type FormRecord = Record<string, unknown>;

export interface UseFormValidationAccessors {
  getDetailRowCount: () => number;
  getDetailRows: () => FormRecord[];
  hasPendingDetailEdits: () => boolean;
  validateForm: () => boolean;
}

export interface UseFormValidationOptions {
  accessors: UseFormValidationAccessors;
  detailFields?: Field[];
  detailNoDataText: string;
  requiredDetail?: boolean;
  validationErrorText: string;
}

export function useFormValidation({
  accessors,
  detailFields,
  detailNoDataText,
  requiredDetail,
  validationErrorText,
}: UseFormValidationOptions): () => boolean {
  return () => {
    const formValid = accessors.validateForm();
    let gridValid = !accessors.hasPendingDetailEdits();
    let detailRequiredMissing = false;
    let detailRowsValid = true;

    if (requiredDetail) {
      gridValid = accessors.getDetailRowCount() > 0;
      if (!gridValid) {
        detailRequiredMissing = true;
      }
    }

    if (detailFields) {
      detailRowsValid = validateDetailRows(accessors.getDetailRows(), detailFields);
    }

    if (!formValid || !gridValid || !detailRowsValid) {
      toast(
        detailRequiredMissing && formValid ? detailNoDataText : validationErrorText,
        detailRequiredMissing && formValid ? 'warning' : 'error',
      );
    }

    return formValid && gridValid && detailRowsValid;
  };
}
