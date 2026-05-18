import type { ReactNode } from 'react';

import type { Field } from '@core/field/Field';

export type FormFieldRenderer = (fields: Field[]) => ReactNode;
