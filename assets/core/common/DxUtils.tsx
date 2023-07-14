import GridData from "../../components/common/GridData";
import {httpClient} from "../http/HttpClient";
import {lastValueFrom} from "rxjs";
import ApiResponse from "../http/ApiResponse";
import CustomStore from "devextreme/data/custom_store";
import DevExpress from "devextreme";
import {FilterDescriptor} from "devextreme/data";
import SortDescriptor = DevExpress.data.SortDescriptor;

class DxUtils {
    private fetchAll(url: string, loadOptions: any): Promise<GridData<any>> {
        let subscription = httpClient.get<any[]>(url, {params: loadOptions});

        return lastValueFrom(subscription)
            .then((response: ApiResponse<any[]>) => {
                return {
                    data: response.data,
                    totalCount: Number(response.headers['x-total-count'])
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
                loadOptions.filter = [...defaultFilterRules, ...userFilter];
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
