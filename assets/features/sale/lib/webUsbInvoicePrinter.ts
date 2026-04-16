import { saleService } from '../services/saleService';

/**
 * ESC/POS USB vendor/product ID pairs for common thermal printer brands.
 * Extend this list as needed for your fleet.
 */
const THERMAL_PRINTER_FILTERS: USBDeviceFilter[] = [
  { vendorId: 0x04b8 }, // Epson
  { vendorId: 0x067b }, // Prolific (USB-to-serial adapters)
  { vendorId: 0x0dd4 }, // Custom / Custom America
  { vendorId: 0x154f }, // SNBC
  { vendorId: 0x0519 }, // Star Micronics
  { vendorId: 0x1fc9 }, // Bixolon
  { vendorId: 0x0fe6 }, // ICS Advent
  { vendorId: 0x28e9 }, // GprinterUSB
];

type WebUsbInvoicePrinter = {
  initialize: () => Promise<void>;
  printSaleInvoice: (saleId: string) => Promise<void>;
};

export class WebUsbNotSupportedError extends Error {
  constructor() {
    super('Web USB API no está disponible en este navegador. Usa Chrome o Edge.');
    this.name = 'WebUsbNotSupportedError';
  }
}

export class WebUsbDeviceNotFoundError extends Error {
  constructor() {
    super('No se encontró una impresora térmica. Conecta la impresora y vuelve a intentarlo.');
    this.name = 'WebUsbDeviceNotFoundError';
  }
}

export const createWebUsbInvoicePrinter = (): WebUsbInvoicePrinter => {
  let device: USBDevice | null = null;

  const getOrRequestDevice = async (): Promise<USBDevice> => {
    if (!navigator.usb) {
      throw new WebUsbNotSupportedError();
    }

    // Re-use a previously paired device if still connected
    if (device?.opened) {
      return device;
    }

    // Try to find a previously authorized device first (no user prompt)
    const paired = await navigator.usb.getDevices();
    const knownVendors = new Set(THERMAL_PRINTER_FILTERS.map((f) => f.vendorId));
    const existing = paired.find((d) => knownVendors.has(d.vendorId));

    if (existing) {
      device = existing;
    } else {
      // Show the browser device picker — user must select their printer once
      device = await navigator.usb.requestDevice({ filters: THERMAL_PRINTER_FILTERS });
    }

    if (!device) {
      throw new WebUsbDeviceNotFoundError();
    }

    return device;
  };

  const openDevice = async (d: USBDevice): Promise<void> => {
    if (!d.opened) {
      await d.open();
    }

    if (d.configuration === null) {
      await d.selectConfiguration(1);
    }

    // Claim the first available bulk-OUT interface
    const iface = d.configuration?.interfaces.find((i) =>
      i.alternates[0]?.endpoints.some((e) => e.direction === 'out' && e.type === 'bulk'),
    );

    if (!iface) {
      throw new Error('No se encontró una interfaz bulk-OUT en la impresora.');
    }

    try {
      await d.claimInterface(iface.interfaceNumber);
    } catch {
      // Already claimed — safe to ignore
    }
  };

  const getEndpointNumber = (d: USBDevice): number => {
    for (const iface of d.configuration?.interfaces ?? []) {
      for (const alt of iface.alternates) {
        const ep = alt.endpoints.find((e) => e.direction === 'out' && e.type === 'bulk');
        if (ep) return ep.endpointNumber;
      }
    }
    throw new Error('No se encontró el endpoint bulk-OUT para enviar datos a la impresora.');
  };

  const initialize = async (): Promise<void> => {
    // Eagerly pair the device so the first print doesn't show a permission prompt mid-sale
    const d = await getOrRequestDevice();
    await openDevice(d);
  };

  const printSaleInvoice = async (saleId: string): Promise<void> => {
    const d = await getOrRequestDevice();
    await openDevice(d);
    const endpointNumber = getEndpointNumber(d);

    await new Promise<void>((resolve, reject) => {
      saleService.printInvoice(saleId).subscribe({
        next: async (response) => {
          try {
            const data = new Uint8Array(response.data);
            // Split into chunks to avoid USB transfer size limits (~16 KB)
            const CHUNK_SIZE = 16384;
            for (let offset = 0; offset < data.length; offset += CHUNK_SIZE) {
              const chunk = data.slice(offset, offset + CHUNK_SIZE);
              await d.transferOut(endpointNumber, chunk);
            }
            resolve();
          } catch (error) {
            reject(error);
          }
        },
        error: reject,
      });
    });
  };

  return { initialize, printSaleInvoice };
};
