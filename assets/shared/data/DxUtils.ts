import { httpClient } from '../api/HttpClient';
import { lastValueFrom } from 'rxjs';
import ApiResponse from '../api/ApiResponse';
import CustomStore from 'devextreme/data/custom_store';
import { FilterDescriptor, SortDescriptor } from 'devextreme/data';
import GridData from '../../types/GridData';
import { DxFilterRule } from '@core/common/FilterRule';
import { LoadOption } from '@core/field/Field';

class DxUtils {
  private async fetchAll(url: string, loadOptions: any): Promise<GridData<any>> {
    const subscription = httpClient.get<any[]>(url, { params: loadOptions });

    return lastValueFrom(subscription).then((response: ApiResponse<any[]>) => {
      let data = response.data;
      if (loadOptions.appendData) {
        data = [...data, ...loadOptions.appendData];
      }
      if (loadOptions.prependData) {
        data = [...loadOptions.prependData, ...data];
      }
      return {
        data: data,
        totalCount: Number(response.headers['x-total-count']),
      } as GridData<any>;
    });
  }

  private async fetchById(url: string, id: any, loadOptions: any): Promise<any> {
    // When id is already an absolute IRI path (e.g. "/api/warehouses/uuid"),
    // call it directly instead of appending it to the base url.
    const resolvedUrl = typeof id === 'string' && id.startsWith('/') ? id : `${url}/${id}`;
    const subscription = httpClient.get<any>(resolvedUrl);

    return lastValueFrom(subscription)
      .then((response: ApiResponse<any>) => {
        return response.data;
      })
      .catch(() => {
        if (loadOptions.prependData) {
          return loadOptions.prependData;
        }
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
    const addIriField = (item: Record<string, unknown>): Record<string, unknown> => ({
      ...item,
      _iri: item['@id'],
    });

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
        if (iriMode) {
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
          return this.fetchById(byKeyUrl ?? url, id, loadOptions).then((item) =>
            item != null ? addIriField(item) : item,
          );
        }
        return this.fetchById(byKeyUrl ?? url, id, loadOptions);
      },
    });
  }
}

export const dxUtils = new DxUtils();
