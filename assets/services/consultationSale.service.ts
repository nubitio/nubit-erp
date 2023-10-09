import {httpClient} from "../core/http/HttpClient";
import {Constants} from "../core/common/Constants";

class ConsultationSaleService {
    getOneById(id: number) {
        return httpClient.get<any>(Constants.API_BASE_URL+'consultation-sales/'+id)
    }
}
export const consultationSaleService = new ConsultationSaleService();

