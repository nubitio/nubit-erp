import { entityField, type DataRecord, type FilterRule } from '@nubitio/crud';

/**
 * Same as `entityField()`, but pre-applies an `isActive = true` filter so
 * disabled master-data records (customers, suppliers, warehouses, cash
 * registers, voucher series, ...) don't show up as choices in new
 * transactions. Disabled records remain fully readable in historical
 * records — this only narrows the lookup dropdown, not the API resource.
 *
 * Call `.filters([...])` afterwards to replace this default (e.g. when a
 * field also needs to scope by branch) — the isActive constraint must be
 * included explicitly again in that case.
 */
export function activeEntityField<TRecord extends DataRecord = DataRecord>(
  url: string,
  valueField: string,
  textField: string,
) {
  return entityField<TRecord>(url, valueField, textField).filters(activeOnlyFilter());
}

/** The single filter rule `activeEntityField` applies by default. Reuse this when a picker needs to combine it with other filters (e.g. branch scoping). */
export function activeOnlyFilter(): FilterRule[] {
  return [{ field: 'isActive', operator: '=', value: 1 }];
}
