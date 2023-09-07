import {httpClient} from "../core/http/HttpClient";
import {Constants} from "../core/common/Constants";

class BranchService {
    getAll() {
        return httpClient.get<any>(`${Constants.API_BASE_URL}branches`);
    }
}

export const branchService = new BranchService();