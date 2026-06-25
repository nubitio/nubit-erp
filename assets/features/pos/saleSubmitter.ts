import { httpClient } from '@shared/api/HttpClient';
import { createLocalSaleAndEnqueue } from '@offline/outbox';
import type { CreateOfflineSaleInput, LocalSale } from '@offline/types';

interface SubmitSaleInput extends CreateOfflineSaleInput {
  isOnline: boolean;
}

type SaleSubmitterResult =
  | {
      mode: 'server';
      serverSaleId: string;
      serverCode: string;
    }
  | {
      mode: 'queued';
      sale: LocalSale;
    };

const buildServerSaleCode = (created: Record<string, unknown>, fallback: string) => {
  const composed = [created.serie, created.numero].filter(Boolean).join('-');
  return composed || String(created.invoice ?? created.id ?? fallback);
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
      );
      const serverSaleId = extractServerSaleId(response.data);

      if (serverSaleId !== null) {
        return {
          mode: 'server',
          serverSaleId,
          serverCode: buildServerSaleCode(response.data, serverSaleId),
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
