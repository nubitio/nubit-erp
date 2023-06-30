import GridData from "../../components/common/GridData";
import {httpClient} from "../http/HttpClient";
import {lastValueFrom} from "rxjs";
import ApiResponse from "../http/ApiResponse";
import CustomStore from "devextreme/data/custom_store";

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

    getDataStore(url: string, idField: string): CustomStore {
        return new CustomStore({
            key: idField,
            load: (loadOptions) => this.fetchAll(url, loadOptions),
            byKey: (key) => this.fetchById(url, key[idField]),
        });
    }
}

export const dxUtils = new DxUtils();
