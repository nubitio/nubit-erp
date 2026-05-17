import { useCallback } from 'react';

type FormRecord = Record<string, unknown>;

export interface FormFieldDataChangedEvent {
  component: {
    option: (name: 'formData') => unknown;
  };
}

export function useFormFieldDataChanged(
  onFieldDataChanged?: (formData: FormRecord) => void,
): ((event: FormFieldDataChangedEvent) => void) | undefined {
  const handleFieldDataChanged = useCallback(
    (event: FormFieldDataChangedEvent) => {
      const snapshot = (event.component.option('formData') ?? {}) as FormRecord;
      onFieldDataChanged?.(snapshot);
    },
    [onFieldDataChanged],
  );

  return onFieldDataChanged ? handleFieldDataChanged : undefined;
}
