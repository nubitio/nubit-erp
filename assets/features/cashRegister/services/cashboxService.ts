import { httpClient } from '../../../shared/api/HttpClient';

class CashboxService {
  getLast() {
    return httpClient.get<any>(`cashbox/last`);
  }
}

export const cashboxService = new CashboxService();
