import { useCallback, useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import './ActionCard.scss';

const API = getCoreApiBaseUrl();

export type ProposedAction = {
  id: string;
  kind: string;
  summary: string;
  payload?: Record<string, unknown>;
  expiresAt?: string;
};

type ActionCardProps = {
  action: ProposedAction;
};

type CardStatus = 'pending' | 'confirming' | 'confirmed' | 'cancelled' | 'error';

export function ActionCard({ action }: ActionCardProps) {
  const [status, setStatus] = useState<CardStatus>('pending');
  const [error, setError] = useState<string | null>(null);

  const post = useCallback(async (path: 'confirm' | 'cancel') => {
    setStatus(path === 'confirm' ? 'confirming' : 'pending');
    setError(null);
    try {
      await httpClient.post(`${API}assistant/actions/${action.id}/${path}`, {});
      setStatus(path === 'confirm' ? 'confirmed' : 'cancelled');
      if (path === 'confirm' && action.kind === 'fill_onboarding' && action.payload) {
        window.dispatchEvent(new CustomEvent('efact:onboarding-fill', { detail: action.payload }));
      }
    } catch (caught) {
      setStatus('error');
      setError(caught instanceof Error ? caught.message : 'No se pudo completar la acción.');
    }
  }, [action.id, action.kind, action.payload]);

  return (
    <div className={`assistant-action-card assistant-action-card--${status}`}>
      <p className="assistant-action-card__summary">{action.summary}</p>
      {status === 'pending' || status === 'error' ? (
        <div className="assistant-action-card__actions">
          <button type="button" className="assistant-action-card__confirm" onClick={() => void post('confirm')}>
            Confirmar
          </button>
          <button type="button" className="assistant-action-card__cancel" onClick={() => void post('cancel')}>
            Descartar
          </button>
        </div>
      ) : null}
      {status === 'confirming' ? <p className="assistant-action-card__hint">Confirmando…</p> : null}
      {status === 'confirmed' ? (
        <p className="assistant-action-card__hint">
          {action.kind === 'fill_onboarding'
            ? 'Campos aplicados al asistente de inicio. Revisa y continúa.'
            : 'Listo. El cambio ya está guardado.'}
        </p>
      ) : null}
      {status === 'cancelled' ? <p className="assistant-action-card__hint">Descartado.</p> : null}
      {error ? <p className="assistant-action-card__error">{error}</p> : null}
    </div>
  );
}
