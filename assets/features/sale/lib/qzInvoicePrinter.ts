import * as qz from 'qz-tray';
import { Constants } from '@core/common/Constants';
import { exceptionalFetch } from '@shared/api/network';
import { saleService } from '../services/saleService';

type QzInvoicePrinter = {
  initialize: () => Promise<void>;
  printSaleInvoice: (saleId: string) => Promise<void>;
};

const arrayBufferToBase64 = (buffer: ArrayBuffer): string => {
  const bytes = new Uint8Array(buffer);
  let binary = '';
  for (let i = 0; i < bytes.byteLength; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  return btoa(binary);
};

export const createQzInvoicePrinter = (): QzInvoicePrinter => {
  let printerName: string | undefined;

  const ensureConnection = async (): Promise<string> => {
    if (!qz.websocket.isActive()) {
      await qz.websocket.connect();
    }

    const activePrinter = printerName ?? (await qz.printers.getDefault());
    if (!printerName) {
      printerName = activePrinter;
    }

    return activePrinter;
  };

  const initialize = async () => {
    qz.security.setSignatureAlgorithm('SHA256');

    qz.security.setCertificatePromise(
      (resolve: (value: string) => void, reject: (reason?: any) => void) => {
        exceptionalFetch(
          `${Constants.API_BASE_URL}qz/certificate`,
          { cache: 'no-store' },
          'sdk-required',
        )
          .then((response) => {
            if (!response.ok) {
              throw new Error('No se pudo cargar el certificado de QZ Tray.');
            }
            return response.text();
          })
          .then(resolve)
          .catch(reject);
      },
    );

    qz.security.setSignaturePromise((toSign: string) => {
      return (resolve: (value: string) => void, reject: (reason?: any) => void) => {
        exceptionalFetch(
          `${Constants.API_BASE_URL}qz/sign`,
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ data: toSign }),
          },
          'sdk-required',
        )
          .then((response) => {
            if (!response.ok) {
              throw new Error('No se pudo firmar el mensaje de QZ Tray.');
            }
            return response.text();
          })
          .then(resolve)
          .catch(reject);
      };
    });

    await ensureConnection();
  };

  const printSaleInvoice = async (saleId: string) => {
    const activePrinter = await ensureConnection();
    const config = qz.configs.create(activePrinter);

    await new Promise<void>((resolve, reject) => {
      saleService.printInvoice(saleId).subscribe({
        next: async (response) => {
          try {
            const base64Data = arrayBufferToBase64(response.data);
            await qz.print(config, [
              {
                type: 'raw',
                format: 'base64',
                data: base64Data,
              },
            ]);
            resolve();
          } catch (error) {
            reject(error);
          }
        },
        error: reject,
      });
    });
  };

  return {
    initialize,
    printSaleInvoice,
  };
};
