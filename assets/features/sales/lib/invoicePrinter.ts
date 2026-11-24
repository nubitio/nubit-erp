import { settings } from '@shared/settings';
import { createWebUsbInvoicePrinter } from './webUsbInvoicePrinter';
import { createLocalAgentInvoicePrinter } from './localAgentInvoicePrinter';

export type InvoicePrinter = {
  initialize: () => Promise<void>;
  /** Sends ESC/POS bytes, however the server builds them (a sale ticket, a shift report...). */
  printDocument: (load: () => Promise<ArrayBuffer>) => Promise<void>;
  printSaleInvoice: (saleId: string) => Promise<void>;
};

/**
 * Factory that returns the appropriate printer implementation based on
 * the `print_method` config setting.
 *
 *   WEBUSB       → Web USB (browser API, no install required)
 *   LOCAL_AGENT  → efact-hardware-agent (Rust, localhost:8765)
 *
 * Defaults to WEBUSB if the setting is missing or unrecognised.
 */
const resolveInvoicePrinter = async (): Promise<InvoicePrinter> => {
  const method = await settings.getPrintMethod();

  if (method === 'LOCAL_AGENT') {
    return createLocalAgentInvoicePrinter();
  }

  return createWebUsbInvoicePrinter();
};

export const createInvoicePrinter = (): InvoicePrinter => {
  let printerPromise: Promise<InvoicePrinter> | null = null;

  const getPrinter = (): Promise<InvoicePrinter> => {
    if (!printerPromise) {
      printerPromise = resolveInvoicePrinter();
    }
    return printerPromise;
  };

  return {
    initialize: async () => {
      const printer = await getPrinter();
      await printer.initialize();
    },
    printDocument: async (load: () => Promise<ArrayBuffer>) => {
      const printer = await getPrinter();
      await printer.printDocument(load);
    },
    printSaleInvoice: async (saleId: string) => {
      const printer = await getPrinter();
      await printer.printSaleInvoice(saleId);
    },
  };
};
