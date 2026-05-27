// eFact domain event namespaces used for cross-component coordination.
// Only include events that are dispatched or subscribed to from OUTSIDE the
// SmartCrudPage that owns the resource — internal event buses are auto-created
// by resolveCrudResource() and don't need to be listed here.
import { createCrudEvents } from '@nubit/crud';

export const companyEvents    = createCrudEvents('company');
export const permissionEvents = createCrudEvents('permission');
export const priceEvents      = createCrudEvents('price');
