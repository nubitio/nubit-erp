import { httpClient } from '../api/HttpClient';

import ApiResponse from '../api/ApiResponse';
import CustomStore from 'devextreme/data/custom_store';
import { FilterDescriptor, SortDescriptor } from 'devextreme/data';
import GridData from './GridData';
import { DxFilterRule } from '@core/field/FilterRule';
import { LoadOption } from '@core/field/Field';

class DxUtils {
  private findPreloadedItem(
    loadOptions: Record<string, unknown>,
    idField: string,
    id: unknown,
  ): Record<string, unknown> | null {
    const prependData = Array.isArray(loadOptions.prependData)
      ? (loadOptions.prependData as Array<Record<string, unknown>>)
      : [];
    const appendData = Array.isArray(loadOptions.appendData)
      ? (loadOptions.appendData as Array<Record<string, unknown>>)
      : [];
    const candidates = [...prependData, ...appendData];

    return candidates.find((item) => item[idField] === id || item['@id'] === id) ?? null;
  }

  private async fetchAll(url: string, loadOptions: any): Promise<GridData<any>> {
    const subscription = httpClient.get<any[]>(url, { params: loadOptions });

    return subscription.then((response: ApiResponse<any[]>) => {
      let data = response.data;
      if (loadOptions.appendData) {
        data = [...data, ...loadOptions.appendData];
      }
      if (loadOptions.prependData) {
        data = [...loadOptions.prependData, ...data];
      }
      const headerCount = Number(response.headers['x-total-count']);
      return {
        data: data,
        totalCount: Number.isFinite(headerCount) ? headerCount : data.length,
      } as GridData<any>;
    });
  }

  private async fetchById(
    url: string,
    id: any,
    loadOptions: any,
    idField: string = 'id',
  ): Promise<any> {
    const preloadedItem = this.findPreloadedItem(loadOptions, idField, id);
    if (preloadedItem) {
      return preloadedItem;
    }

    // When id is already an absolute IRI path (e.g. "/api/warehouses/uuid"),
    // call it directly instead of appending it to the base url.
    const resolvedUrl = typeof id === 'string' && id.startsWith('/') ? id : `${url}/${id}`;
    const subscription = httpClient.get<any>(resolvedUrl);

    return subscription
      .then((response: ApiResponse<any>) => {
        return response.data;
      })
      .catch(() => {
        return this.findPreloadedItem(loadOptions, idField, id);
      });
  }

  private normalizeFilterRules(
    filterRules: FilterDescriptor[],
    defaultFilterRules: FilterDescriptor[],
  ): FilterDescriptor[] {
    const filters: FilterDescriptor[] = [];
    defaultFilterRules.forEach((rule: DxFilterRule) => {
      filters.push([rule.field, rule.operator, rule.value]);
    });
    if (filterRules.length === 3 && !(typeof filterRules[0] === 'object')) {
      filters.push([filterRules[0], filterRules[1], filterRules[2]]);
    } else {
      filterRules.forEach((rule) => {
        filters.push(rule);
      });
    }
    return filters;
  }

  makeFilterRules(filterRules: DxFilterRule[]): string {
    return filterRules
      .map((filter) => `filter[]=["${filter.field}","${filter.operator}","${filter.value}"]`)
      .join('&');
  }

  getDataStore(
    url: string,
    idField: string,
    byKeyUrl: string | null = null,
    defaultFilterRules: FilterDescriptor[] = [],
    defaultSortRules: SortDescriptor<any>[] = [],
    options: LoadOption[] = [],
    iriMode: boolean = false,
  ): CustomStore {
    /**
     * IRI mode: the API stores relation values as full IRIs (e.g. "/api/warehouses/uuid").
     * DevExtreme cannot use "@id" as a valueExpr key because "@" is not a valid JS identifier
     * in dot-notation access paths used by some DX code paths.
     *
     * When iriMode=true, each loaded item gets a synthetic "_iri" field copied from "@id":
     *   { "@id": "/api/warehouses/uuid", "name": "Almacén Central", "_iri": "/api/warehouses/uuid" }
     *
     * The lookup is then configured with valueExpr="_iri" / displayExpr="name", so:
     *  - valueExpr "_iri" matches the full IRI stored in the parent record
     *  - displayExpr renders the human-readable name
     *  - no "@" character is ever used as a JS property accessor
     */
    const addIriField = (item: Record<string, unknown>): Record<string, unknown> => {
      // Prefer the Hydra "@id" IRI when present.
      // Fallback: some endpoints (e.g. /api/categories) expose only "id" without "@id".
      // In that case, synthesize the IRI from the base url + id so that iriMode
      // lookups still work (BUG: category UUID id breaks SelectBox required validation).
      const itemId = item['id'] ?? item['code'] ?? item['uuid'] ?? item['slug'];
      const iri =
        typeof item['@id'] === 'string'
          ? item['@id']
          : typeof itemId === 'string' || typeof itemId === 'number'
            ? `${url}/${itemId}`
            : undefined;
      return { ...item, _iri: iri };
    };

    return new CustomStore({
      key: idField,
      load: (loadOptions) => {
        const userFilter = (loadOptions.filter ?? []) as FilterDescriptor[];
        loadOptions.filter = this.normalizeFilterRules(userFilter, defaultFilterRules);
        const userSort = (loadOptions.sort ?? []) as any[];
        if (
          userSort.length === 1 &&
          userSort[0].selector === idField &&
          defaultSortRules.length === 0
        ) {
          loadOptions.sort = [];
        } else if (userSort.length === 1 && userSort[0].selector === idField) {
          loadOptions.sort = [...defaultSortRules];
        } else {
          loadOptions.sort = userSort;
        }
        options.forEach((option) => {
          for (const key in option) {
            if (Object.prototype.hasOwnProperty.call(option, key)) {
              // LoadOptions does not expose an index signature; cast is safe because
              // LoadOption values are arbitrary query params forwarded to the server.
              (loadOptions as Record<string, unknown>)[key] = option[key];
            }
          }
        });

        // Strip DevExtreme-internal params not declared in the backend filter.
        // DevExtreme unconditionally appends searchOperation=contains to every
        // DataSource request (including plain list fetches); API Platform uses
        // strict parameter whitelisting and returns 404 for unknown params.
        // searchValue is only meaningful when paired with searchExpr — drop it
        // when searchExpr is absent to avoid sending a dangling param.
        const opts = loadOptions as Record<string, unknown>;
        delete opts['searchOperation'];
        if (opts['searchExpr'] === undefined) {
          delete opts['searchValue'];
        }

        if (iriMode) {
          // When DevExtreme tries to resolve a SelectBox value it emits a load
          // with a single equality filter on the key field ('_iri').  That field
          // is synthetic (does not exist in the backend) so forwarding it to the
          // API would produce a Doctrine / API-Platform error.
          // Detect this pattern (both flat and nested) and short-circuit to a
          // direct byKey fetch instead.
          const filter = loadOptions.filter as unknown[];
          // Normalise: unwrap [[field, op, val]] → [field, op, val]
          const flat =
            Array.isArray(filter) &&
            filter.length === 1 &&
            Array.isArray(filter[0])
              ? (filter[0] as unknown[])
              : filter;
          if (
            Array.isArray(flat) &&
            flat.length === 3 &&
            flat[0] === '_iri' &&
            flat[1] === '=' &&
            typeof flat[2] === 'string'
          ) {
            const iriValue = flat[2] as string;
            return this.fetchById(byKeyUrl ?? url, iriValue, {}, idField).then((item) =>
              item != null
                ? { data: [addIriField(item)], totalCount: 1 }
                : { data: [], totalCount: 0 },
            );
          }

          return this.fetchAll(url, loadOptions).then((result) => ({
            ...result,
            data: result.data.map(addIriField),
          }));
        }
        return this.fetchAll(url, loadOptions);
      },
      byKey: (key) => {
        const id = typeof key === 'object' ? (key as Record<string, unknown>)[idField] : key;
        if (id === undefined) return Promise.resolve(null);
        const loadOptions: Record<string, unknown> = {};
        options.forEach((option) => {
          for (const key in option) {
            if (Object.prototype.hasOwnProperty.call(option, key)) {
              loadOptions[key] = option[key];
            }
          }
        });
        if (iriMode) {
          return this.fetchById(byKeyUrl ?? url, id, loadOptions, idField).then((item) =>
            item != null ? addIriField(item) : item,
          );
        }
        return this.fetchById(byKeyUrl ?? url, id, loadOptions, idField);
      },
    });
  }
}

export const dxUtils = new DxUtils();
