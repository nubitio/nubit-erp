import {httpClient} from "../../../core/http/HttpClient";
import {Constants} from "../../../core/common/Constants";

const cashRegisterService = () => {
    const openCashRegister = (id: string, openingBalance: number) => {
        return httpClient.post<any>(Constants.API_BASE_URL + `cash-registers/${id}/open`, {
            id: Number(id),
            openingBalance: openingBalance.toFixed(2)
        });
    }

    const closeCashRegister = (id: string) => {
        return httpClient.put<any>(Constants.API_BASE_URL + `cash-registers/${id}/close`, {id: id});
    }

    const getMovements = (id: string) => {
        return httpClient.get<any>(Constants.API_BASE_URL + `cash-registers/${id}/movements`);
    }

    return {
        openCashRegister,
        closeCashRegister,
        getMovements
    }
}

export default cashRegisterService;
