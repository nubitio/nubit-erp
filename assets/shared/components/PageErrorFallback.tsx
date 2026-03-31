import React from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

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
    <div
      role="alert"
      aria-live="assertive"
      style={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '3rem 2rem',
        gap: '0.75rem',
        textAlign: 'center',
      }}
    >
      <h2 style={{ fontSize: '1.25rem', fontWeight: 600, margin: 0 }}>{t('common.error')}</h2>
      <p style={{ color: '#6b7280', margin: 0, fontSize: '0.875rem' }}>
        Esta sección encontró un error. Puedes volver al inicio o intentar de nuevo.
      </p>
      <div style={{ display: 'flex', gap: '0.5rem', marginTop: '0.5rem' }}>
        <button
          type="button"
          onClick={() => navigate('/dashboard')}
          style={{
            padding: '0.5rem 1.25rem',
            borderRadius: '0.375rem',
            border: '1px solid #d1d5db',
            background: '#ffffff',
            fontWeight: 500,
            fontSize: '0.8125rem',
            cursor: 'pointer',
          }}
        >
          Ir al inicio
        </button>
        <button
          type="button"
          onClick={() => window.location.reload()}
          style={{
            padding: '0.5rem 1.25rem',
            borderRadius: '0.375rem',
            border: 'none',
            background: '#1463ff',
            color: '#ffffff',
            fontWeight: 500,
            fontSize: '0.8125rem',
            cursor: 'pointer',
          }}
        >
          {t('common.retry')}
        </button>
      </div>
      {import.meta.env.DEV && error && (
        <details
          style={{
            marginTop: '1rem',
            maxWidth: '40rem',
            textAlign: 'left',
            fontSize: '0.75rem',
            color: '#ef4444',
            whiteSpace: 'pre-wrap',
            wordBreak: 'break-word',
          }}
        >
          <summary style={{ cursor: 'pointer', fontWeight: 600 }}>Detalles del error (dev)</summary>
          <pre style={{ margin: '0.5rem 0 0', overflow: 'auto' }}>
            {error.toString()}
            {'\n\n'}
            {error.stack}
          </pre>
        </details>
      )}
    </div>
  );
}
