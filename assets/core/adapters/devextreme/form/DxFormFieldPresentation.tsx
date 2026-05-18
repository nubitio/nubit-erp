import type { Field } from '@core/field/Field';
import { getFormFieldPresentation } from '@core/form/FormFieldPresentation';

export function getDxFieldPresentation(field: Field) {
  const presentation = getFormFieldPresentation(field);

  return {
    editorOptions: presentation.useFloatingLabel
      ? {
          label: field.label,
          labelMode: 'floating',
        }
      : {},
    label: {
      text: field.label,
      visible: !presentation.useFloatingLabel && !presentation.hideLabel,
    },
    cssClass: field.hidden ? 'dx-hidden' : '',
  };
}
