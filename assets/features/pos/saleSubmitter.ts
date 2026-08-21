import { httpClient } from '@shared/api/HttpClient';
import { createLocalSaleAndEnqueue } from '@offline/outbox';
import type { CreateOfflineSaleInput, LocalSale } from '@offline/types';

interface SubmitSaleInput extends CreateOfflineSaleInput {
  isOnline: boolean;
  idempotencyKey: string;
}

export type SaleSubmitterResult =
  | {
      mode: 'server';
      serverSaleId: string;
      serverCode: string;
      invoiceFullNumber: string | null;
      publicReceiptUrl: string | null;
      documentType: string | null;
    }
  | {
      mode: 'queued';
      sale: LocalSale;
    };

const asTrimmedString = (value: unknown): string | null => {
  if (typeof value !== 'string') {
    return null;
  }
  const trimmed = value.trim();
  return trimmed === '' ? null : trimmed;
};

export const generateIdempotencyKey = (): string => {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
    const random = (Math.random() * 16) | 0;
    const value = char === 'x' ? random : (random & 0x3) | 0x8;
    return value.toString(16);
  });
};

const looksLikeUuid = (value: string): boolean =>
  /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value);

const buildServerSaleCode = (created: Record<string, unknown>, fallback: string) => {
  const invoiceFullNumber = asTrimmedString(created.invoiceFullNumber);
  if (invoiceFullNumber && !looksLikeUuid(invoiceFullNumber)) {
    return invoiceFullNumber;
  }

  const composed = [created.series ?? created.serie, created.number ?? created.numero]
    .map((part) => asTrimmedString(part))
    .filter((part): part is string => part !== null)
    .join('-');
  if (composed) {
    return composed;
  }

  const rawFallback = String(created.invoice ?? created.id ?? fallback);
  return looksLikeUuid(rawFallback) ? '' : rawFallback;
};

const extractServerSaleId = (created: Record<string, unknown>): string | null => {
  if (created.id != null) {
    return String(created.id);
  }

  const iri = typeof created['@id'] === 'string' ? created['@id'] : '';
  const match = iri.match(/\/([^/]+)$/);
  return match?.[1] ?? null;
};

const isNetworkFailure = (error: unknown): boolean => {
  if (error instanceof TypeError) return true;
  if (error instanceof Error) {
    const err = error as Error & { status?: number };
    return typeof err.status === 'number' && err.status >= 500;
  }
  return false;
};

export async function submitSale(input: SubmitSaleInput): Promise<SaleSubmitterResult> {
  if (input.isOnline) {
    try {
      const response = await httpClient.post<Record<string, unknown>>(
        input.endpointUrl,
        input.requestBody,
        {
          headers: {
            'Idempotency-Key': input.idempotencyKey,
          },
        },
      );
      const serverSaleId = extractServerSaleId(response.data);

      if (serverSaleId !== null) {
        return {
          mode: 'server',
          serverSaleId,
          serverCode: buildServerSaleCode(response.data, serverSaleId),
          invoiceFullNumber: asTrimmedString(response.data.invoiceFullNumber),
          publicReceiptUrl: asTrimmedString(response.data.publicReceiptUrl),
          documentType: asTrimmedString(response.data.documentType),
        };
      }

      throw new Error('La venta fue registrada, pero la respuesta no incluyo un identificador.');
    } catch (error) {
      if (!isNetworkFailure(error)) {
        throw error;
      }
    }
  }

  const { sale } = await createLocalSaleAndEnqueue(input);
  return { mode: 'queued', sale };
}
