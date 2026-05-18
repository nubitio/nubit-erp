import type { FormEventNames } from '../event/EventTypes';
import type { Field } from '../field/Field';

export interface UseFormSubmitOptions {
  url: string;
  detailUrl?: string;
  fields: Field[];
  detailFields?: Field[];
  detailPropertyName: string;
  format?: 'json' | 'multipart';
  events?: FormEventNames;
  onSaveSuccess?: (response: unknown) => void;
  onSaveError?: (error?: unknown) => void;
  onDeleteSuccess?: (response: unknown) => void;
  onDeleteError?: (error?: unknown) => void;
  onLoadingChange?: (loading: boolean) => void;
}
