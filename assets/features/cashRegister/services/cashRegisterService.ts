import {httpClient} from "../../../core/http/HttpClient";
import {Constants} from "../../../core/common/Constants";

const cashRegisterService = () => {
    const openCashRegister = (id: string, openingBalance: number) => {
        const amount = Number(openingBalance ?? 0);
        return httpClient.post<any>(Constants.API_BASE_URL + `cash-registers/${id}/open`, {
            id: Number(id),
            openingBalance: Number(amount.toFixed(2))
        });
    }

    const closeCashRegister = (id: string, closureData: any) => {
        return httpClient.post<any>(Constants.API_BASE_URL + `cash-registers/${id}/close`, {closureItems: closureData});
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
