import {httpClient} from "../core/http/HttpClient";

class KardexService {
    getAll(dateFrom: string, dateTo: string) {
        return httpClient.get<any>(`kardex/get?dateFrom=${dateFrom}&dateTo=${dateTo}`);
    }

    getDetail(productId: number, dateFrom: string, dateTo: string) {
        return httpClient.get<any>(`kardex/${productId}/detail?dateFrom=${dateFrom}&dateTo=${dateTo}`);
    }
}

export const kardexService = new KardexService();
