import type { FormLayout } from '@core/crud/FormLayout';
import type { FormEventNames } from '@core/event/EventTypes';
import type { Field } from '@core/field/Field';

export interface FormViewOptions {
  className?: string;
  url: string;
  detailUrl?: string;
  fields: Field[];
  detailFields?: Field[];
  detailPropertyName?: string;
  allowAdding?: boolean;
  allowUpdating?: boolean;
  allowDeleting?: boolean;
  events?: FormEventNames;
  editable?: boolean;
  format?: 'json' | 'multipart';
  requiredDetail?: boolean;
  /** @deprecated no tiene efecto, IRI siempre se aplica. */
  useIRI?: boolean;
  /** Called after a successful POST or PATCH. Receives the server response. */
  onSaveSuccess?: (response: unknown) => void;
  /** Called when POST or PATCH fails after HTTP error handling. */
  onSaveError?: (error?: unknown) => void;
  /** Called after a successful DELETE. */
  onDeleteSuccess?: (response: unknown) => void;
  /** Called when DELETE fails. */
  onDeleteError?: (error?: unknown) => void;
  /** Called whenever the loading state changes. */
  onLoadingChange?: (loading: boolean) => void;
  /** Optional layout descriptor for tabs or collapsible sections. */
  formLayout?: FormLayout;
  /** Called whenever any form field value changes with a full form data snapshot. */
  onFieldDataChanged?: (formData: Record<string, unknown>) => void;
  /** Latest runtime-computed values pushed down by SmartCrudPage. */
  computedValues?: Record<string, unknown>;
  /** @deprecated no longer used, sidebar is always single column. */
  sidebarColCount?: never;
}
