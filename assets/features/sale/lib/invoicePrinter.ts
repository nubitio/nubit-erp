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
export const createInvoicePrinter = (): InvoicePrinter => {
  if (settings.isFeatureEnabledCached(Feature.LOCAL_AGENT_PRINT)) {
    return createLocalAgentInvoicePrinter();
  }

  if (settings.isFeatureEnabledCached(Feature.WEBUSB_PRINT)) {
    return createWebUsbInvoicePrinter();
  }

  return createQzInvoicePrinter();
};
