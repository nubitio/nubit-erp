import { MutableRefObject } from 'react';

import toast from '@shared/lib/SharedUtils';
import type { Field } from '@core/field/Field';

import { validateDetailRows } from './FormDetailValidation';

type FormRecord = Record<string, unknown>;

export interface ValidationFormRef {
  instance: () => {
    validate: () => { isValid?: boolean } | undefined;
  };
}

export interface ValidationGridRef {
  instance: () => {
    hasEditData: () => boolean;
    totalCount: () => number;
    getDataSource: () => {
      items: () => unknown[];
    };
  };
}

export interface UseFormValidationOptions {
  detailFields?: Field[];
  detailNoDataText: string;
  formRef: MutableRefObject<ValidationFormRef | null>;
  gridRef: MutableRefObject<ValidationGridRef | null>;
  requiredDetail?: boolean;
  validationErrorText: string;
}

export function useFormValidation({
  detailFields,
  detailNoDataText,
  formRef,
  gridRef,
  requiredDetail,
  validationErrorText,
}: UseFormValidationOptions): () => boolean {
  return () => {
    const formValidation = formRef.current?.instance().validate();
    const formValid = formValidation?.isValid ?? true;
    let gridValid = !gridRef.current?.instance().hasEditData();
    let detailRequiredMissing = false;
    let detailRowsValid = true;

    if (requiredDetail) {
      gridValid = (gridRef.current?.instance().totalCount() ?? 0) > 0;
      if (!gridValid) {
        detailRequiredMissing = true;
      }
    }

    if (detailFields) {
      const detailRows = (gridRef.current?.instance().getDataSource().items() ??
        []) as FormRecord[];
      detailRowsValid = validateDetailRows(detailRows, detailFields);
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
