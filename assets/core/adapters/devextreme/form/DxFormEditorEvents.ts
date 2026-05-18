import { dispatch } from '@core/event/EventHook';
import type { Field } from '@core/field/Field';
import type { FormOnChangeFn } from '@core/field/FieldCallbacks';
import { FORM_EVENTS } from '@core/form/FormEvents';

export function getDxEditorValueChangedHandler(field: Field): (e: { value: unknown }) => void {
  return (e: { value: unknown }) => {
    dispatch(FORM_EVENTS.FIELD_CHANGED, { field: field.name, value: e.value });
    if (field.onChange) {
      (field.onChange as FormOnChangeFn)(e.value);
    }
  };
}
