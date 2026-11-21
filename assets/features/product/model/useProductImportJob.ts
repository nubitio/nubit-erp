import { useCallback, useRef, useState } from 'react';
import { MercureManager, useMercureHub } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';

export type ProductImportStatus = 'idle' | 'uploading' | 'processing' | 'completed' | 'failed';

export interface ProductImportProgress {
  current: number;
  total: number;
}

export interface ProductImportResult {
  created: number;
  updated: number;
  errors: Array<{ row: number; message: string }>;
}

interface MercureEvent {
  jobId: string;
  status: 'processing' | 'completed' | 'failed';
  progress?: ProductImportProgress;
  result?: ProductImportResult;
  error?: string;
}

export function progressPercent(progress: ProductImportProgress | null): number {
  if (!progress || progress.total === 0) return 0;
  return Math.round((progress.current / progress.total) * 100);
}

/**
 * Uploads a products spreadsheet and follows the server-side import job over Mercure. Shared by the
 * catalog's import dialog and the first-run import wizard so both report progress the same way.
 */
export function useProductImportJob(onDone?: () => void) {
  const hubUrl = useMercureHub();
  const [status, setStatus] = useState<ProductImportStatus>('idle');
  const [progress, setProgress] = useState<ProductImportProgress | null>(null);
  const [result, setResult] = useState<ProductImportResult | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const jobIdRef = useRef<string | null>(null);

  const reset = useCallback(() => {
    setStatus('idle');
    setProgress(null);
    setResult(null);
    setErrorMessage(null);
    jobIdRef.current = null;
  }, []);

  const subscribe = useCallback(
    (topic: string) => {
      if (!hubUrl) return;
      const handler = (data: unknown) => {
        const event = data as MercureEvent;
        if (event.status === 'processing' && event.progress) {
          setProgress(event.progress);
        } else if (event.status === 'completed' && event.result) {
          setResult(event.result);
          setStatus('completed');
          MercureManager.unsubscribe(topic, handler);
          onDone?.();
        } else if (event.status === 'failed') {
          setErrorMessage(event.error ?? 'Error desconocido');
          setStatus('failed');
          MercureManager.unsubscribe(topic, handler);
        }
      };
      MercureManager.subscribe(topic, handler);
    },
    [hubUrl, onDone],
  );

  const start = useCallback(
    async (file: File) => {
      setStatus('uploading');
      setProgress(null);
      setResult(null);
      setErrorMessage(null);
      const formData = new FormData();
      formData.append('file', file);
      try {
        const response = await httpClient.post<{ jobId: string; status: string; topic: string }>(
          '/api/v1/products/import',
          formData,
        );
        jobIdRef.current = response.data.jobId;
        setStatus('processing');
        subscribe(response.data.topic);
      } catch (err) {
        setErrorMessage(err instanceof Error ? err.message : 'Error al subir el archivo');
        setStatus('failed');
      }
    },
    [subscribe],
  );

  return {
    status,
    progress,
    result,
    errorMessage,
    isRunning: status === 'uploading' || status === 'processing',
    start,
    reset,
  };
}
