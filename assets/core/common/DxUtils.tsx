import {httpClient} from "../http/HttpClient";
import {lastValueFrom} from "rxjs";
import ApiResponse from "../http/ApiResponse";
import CustomStore from "devextreme/data/custom_store";
import DevExpress from "devextreme";
import {FilterDescriptor} from "devextreme/data";
import GridData from "../../types/GridData";
import {DxFilterRule} from "./FilterRule";
import SortDescriptor = DevExpress.data.SortDescriptor;

class DxUtils {
    private fetchAll(url: string, loadOptions: any): Promise<GridData<any>> {
        let subscription = httpClient.get<any[]>(url, {params: loadOptions});

        return lastValueFrom(subscription)
            .then((response: ApiResponse<any[]>) => {
                return {
                    data: response.data,
                    totalCount: Number(response.headers['x-total-count'] ?? response.data.length)
                } as GridData<any>;
            });
    }

    private fetchById(url: string, id: any): Promise<any> {
        let subscription = httpClient.get<any>(`${url}/${id}`);

        return lastValueFrom(subscription)
            .then((response: ApiResponse<any>) => {
                return response.data;
            });
    }

    private normalizeFilterRules(filterRules: FilterDescriptor[], defaultFilterRules: FilterDescriptor[]): FilterDescriptor[] {
        let filters: FilterDescriptor[] = [];
        defaultFilterRules.forEach((rule: DxFilterRule) => {
            filters.push([rule.field, rule.operator, rule.value]);
        })
        if (filterRules.length === 3 && !(typeof filterRules[0] === 'object')) {
            filters.push([filterRules[0], filterRules[1], filterRules[2]]);
        } else {
            filterRules.forEach((rule) => {
                filters.push(rule);
            })
        }
        return filters;
    }

    getDataStore(
        url: string,
        idField: string,
        byKeyUrl: string | null = null,
        defaultFilterRules: FilterDescriptor[] = [],
        defaultSortRules: SortDescriptor<any>[] = []
    ): CustomStore {
        return new CustomStore({
            key: idField,
            load: (loadOptions) => {
                let userFilter = (loadOptions.filter ?? []) as FilterDescriptor[];
                loadOptions.filter = this.normalizeFilterRules(userFilter, defaultFilterRules);
                let userSort = (loadOptions.sort ?? []) as SortDescriptor<any>[];
                loadOptions.sort = [...defaultSortRules, ...userSort];
                return this.fetchAll(url, loadOptions)
            },
            byKey: (key) => {
                let id = typeof key === 'object' ? key[idField] : key;
                return this.fetchById(byKeyUrl ?? url, id)
            }
        });
    }
}

export const dxUtils = new DxUtils();
