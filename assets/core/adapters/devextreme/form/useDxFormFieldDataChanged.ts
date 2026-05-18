import { useCallback, type ComponentProps } from 'react';
import Form from 'devextreme-react/form';

import type { FormDataChangedHandler, FormDataSnapshot } from '@core/form/FormDataSnapshot';

type FieldDataChangedHandler = ComponentProps<typeof Form>['onFieldDataChanged'];
type FieldDataChangedCallback = NonNullable<FieldDataChangedHandler>;

export function useDxFormFieldDataChanged(
  onFormDataChanged?: FormDataChangedHandler,
): FieldDataChangedHandler | undefined {
  const handleFieldDataChanged = useCallback<FieldDataChangedCallback>((event) => {
    const snapshot = (event.component.option('formData') ?? {}) as FormDataSnapshot;
    onFormDataChanged?.(snapshot);
  }, [onFormDataChanged]);

  return onFormDataChanged ? handleFieldDataChanged : undefined;
}
