import type { TimelineItemStatus } from '@nubitio/ui';

export interface DocumentLifecycleInput {
  createdAt?: string | null;
  sunatStatus?: string | null;
}

export interface DocumentLifecycleStep {
  key: string;
  title: string;
  status: TimelineItemStatus;
  timestamp?: string;
  dateTime?: string;
}

function isSunatRejection(status: string): boolean {
  if (!/^\d+$/.test(status)) return false;
  const code = Number.parseInt(status, 10);
  return code >= 2000 && code <= 3999;
}

function resolveAcceptedStep(sunatStatus: string): Pick<DocumentLifecycleStep, 'status' | 'title'> {
  if (sunatStatus === '0') {
    return { status: 'complete', title: 'Aceptado por SUNAT' };
  }
  if (isSunatRejection(sunatStatus)) {
    return { status: 'error', title: `Rechazado · código ${sunatStatus}` };
  }
  if (sunatStatus && sunatStatus !== 'pendiente') {
    return { status: 'error', title: `Rechazado · ${sunatStatus}` };
  }
  return { status: 'pending', title: 'Aceptado por SUNAT' };
}

export function buildSunatLifecycleSteps(
  document: DocumentLifecycleInput,
  formatTimestamp: (iso: string) => string,
): DocumentLifecycleStep[] {
  const sunatStatus = document.sunatStatus?.trim() ?? '';
  const createdAt = document.createdAt?.trim() ?? '';
  const hasBeenSent = sunatStatus.length > 0;

  const draftStep: DocumentLifecycleStep = {
    key: 'draft',
    title: 'Borrador creado',
    status: 'complete',
    ...(createdAt
      ? {
          dateTime: createdAt,
          timestamp: formatTimestamp(createdAt),
        }
      : {}),
  };

  const sentStep: DocumentLifecycleStep = {
    key: 'sent',
    title: 'Enviado a SUNAT',
    status: hasBeenSent ? 'complete' : 'pending',
  };

  const awaitingStep: DocumentLifecycleStep = {
    key: 'awaiting-cdr',
    title: 'Esperando CDR',
    status: !hasBeenSent ? 'pending' : sunatStatus === 'pendiente' ? 'current' : 'complete',
  };

  const accepted = resolveAcceptedStep(sunatStatus);

  const acceptedStep: DocumentLifecycleStep = {
    key: 'accepted',
    title: accepted.title,
    status: accepted.status,
  };

  return [draftStep, sentStep, awaitingStep, acceptedStep];
}
