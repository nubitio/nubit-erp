import React from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { Button, EmptyState } from '@nubitio/ui';

/**
 * Lightweight error fallback for use inside the app layout (SideNavBarLayout).
 * Unlike the root ErrorBoundary fallback, this renders inline within the
 * content area and offers navigation back to the dashboard instead of a
 * full page reload.
 */
export function PageErrorFallback({ error }: { error: Error | null }) {
  const { t } = useTranslation();
  const navigate = useNavigate();

  return (
    <EmptyState
      variant="danger"
      size="md"
      title={t('common.error')}
      description="Esta sección encontró un error. Puedes volver al inicio o intentar de nuevo."
      action={
        <>
          <Button variant="secondary" onClick={() => navigate('/dashboard')}>
            Ir al inicio
          </Button>
          <Button variant="primary" onClick={() => window.location.reload()}>
            {t('common.retry')}
          </Button>
          {import.meta.env.DEV && error && (
            <details
              style={{
                textAlign: 'left',
                maxWidth: '40rem',
                width: '100%',
                marginTop: 'var(--space-3)',
              }}
            >
              <summary
                style={{
                  cursor: 'pointer',
                  fontWeight: 'var(--font-weight-semibold)',
                  color: 'var(--text-secondary)',
                  fontSize: 'var(--font-size-xs)',
                }}
              >
                Detalles del error (dev)
              </summary>
              <pre
                style={{
                  margin: 'var(--space-2) 0 0',
                  overflow: 'auto',
                  fontSize: 'var(--font-size-xs)',
                  color: 'var(--error-color)',
                  whiteSpace: 'pre-wrap',
                  wordBreak: 'break-word',
                }}
              >
                {error.toString()}
                {'\n\n'}
                {error.stack}
              </pre>
            </details>
          )}
        </>
      }
    />
  );
}
