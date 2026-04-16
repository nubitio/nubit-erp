import { settings, Feature } from '@app/Settings';
import { createQzInvoicePrinter } from './qzInvoicePrinter';
import { createWebUsbInvoicePrinter } from './webUsbInvoicePrinter';
import { createLocalAgentInvoicePrinter } from './localAgentInvoicePrinter';

export type InvoicePrinter = {
  initialize: () => Promise<void>;
  printSaleInvoice: (saleId: string) => Promise<void>;
};

/**
 * Factory that returns the appropriate printer implementation based on
 * tenant feature flags (resolved synchronously from cache).
 *
 * Priority:
 *   1. feature_local_agent_print → efact-printer-agent (Rust, localhost:8765)
 *   2. feature_webusb_print      → Web USB (browser API, no install)
 *   3. fallback                  → QZ Tray (legacy)
 */
const resolveInvoicePrinter = async (): Promise<InvoicePrinter> => {
  if (await settings.isFeatureEnabled(Feature.LOCAL_AGENT_PRINT)) {
    return createLocalAgentInvoicePrinter();
  }

  if (await settings.isFeatureEnabled(Feature.WEBUSB_PRINT)) {
    return createWebUsbInvoicePrinter();
  }

  return createQzInvoicePrinter();
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
    printSaleInvoice: async (saleId: string) => {
      const printer = await getPrinter();
      await printer.printSaleInvoice(saleId);
    },
  };
};
