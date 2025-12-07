import { httpClient } from '../http/HttpClient';
import { lastValueFrom } from 'rxjs';
import ApiResponse from '../http/ApiResponse';
import CustomStore from 'devextreme/data/custom_store';
import { FilterDescriptor, SortDescriptor } from 'devextreme/data';
import GridData from '../../types/GridData';
import { DxFilterRule } from './FilterRule';
import { LoadOption } from '../field/Field';

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
    const subscription = httpClient.get<any>(`${url}/${id}`);

    return lastValueFrom(subscription)
      .then((response: ApiResponse<any>) => {
        return response.data;
      })
      .catch((error) => {
        console.log(loadOptions);
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
  ): CustomStore {
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
            if (option.hasOwnProperty(key)) {
              loadOptions[key] = option[key];
            }
          }
        });
        return this.fetchAll(url, loadOptions);
      },
      byKey: (key) => {
        const id = typeof key === 'object' ? key[idField] : key;
        if (id === undefined) return Promise.resolve(null);
        const loadOptions = {};
        options.forEach((option) => {
          for (const key in option) {
            if (option.hasOwnProperty(key)) {
              loadOptions[key] = option[key];
            }
          }
        });
        return this.fetchById(byKeyUrl ?? url, id, loadOptions);
      },
    });
  }
}

export const dxUtils = new DxUtils();
