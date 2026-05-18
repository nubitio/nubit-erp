import { Template } from 'devextreme-react/core/template';

import type { Field } from '@core/field/Field';

export const buildTemplate = (field: Field) => {
  if (field.itemFormatter) {
    return <Template name={field.name} render={field.itemFormatter} />;
  }
};
