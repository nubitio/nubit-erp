import { exceptionalFetch } from '@shared/api/network';
import { saleService } from '../services/saleService';
import type { InvoicePrinter } from './invoicePrinter';

const AGENT_BASE_URL = 'http://localhost:8765';

export class LocalAgentNotAvailableError extends Error {
  constructor() {
    super(
      'efact-hardware-agent no está disponible. ' +
        'Asegúrate de que el agente local esté instalado y en ejecución.',
    );
    this.name = 'LocalAgentNotAvailableError';
  }
}

export class LocalAgentPrintError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'LocalAgentPrintError';
  }
}

export const createLocalAgentInvoicePrinter = (): InvoicePrinter => {
  const initialize = async (): Promise<void> => {
    let ok = false;
    try {
      const res = await exceptionalFetch(`${AGENT_BASE_URL}/health`, { mode: 'cors' }, 'sdk-required');
      ok = res.ok;
    } catch {
      // network error → agent not running
    }
    if (!ok) throw new LocalAgentNotAvailableError();
  };

  const printSaleInvoice = async (saleId: string): Promise<void> => {
    const response = await saleService.printInvoice(saleId).then((r) => r.data);

    let res: Response;
    try {
      res = await exceptionalFetch(`${AGENT_BASE_URL}/print`, {
        method: 'POST',
        mode: 'cors',
        headers: { 'Content-Type': 'application/octet-stream' },
        body: response,
      }, 'sdk-required');
    } catch {
      throw new LocalAgentNotAvailableError();
    }

    if (!res.ok) {
      const body = await res.text().catch(() => res.statusText);
      throw new LocalAgentPrintError(`El agente respondió ${res.status}: ${body}`);
    }
  };

  return { initialize, printSaleInvoice };
};
