import { useEffect } from 'react';
import { Link, Navigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Button } from '@nubitio/ui';
import { AccountPortalLayout } from '../components/AccountPortalLayout';
import { accountPortalClient } from '../api/accountPortalClient';
import { useAccountSession } from '../hooks/useAccountSession';
import { useApToast } from '../providers/ApToastProvider';

export function StatusPage() {
  const { token = '' } = useParams();
  const session = useAccountSession();
  const [searchParams] = useSearchParams();
  const { notify } = useApToast();

  const status = useQuery({
    queryKey: ['provision-status', token],
    queryFn: () => accountPortalClient.getProvisionStatus(token),
    enabled: Boolean(token) && session.data?.authenticated === true,
    refetchInterval: (query) => {
      const value = query.state.data?.status;
      return value === 'completed' || value === 'failed' ? false : 3000;
    },
  });

  useEffect(() => {
    if (searchParams.get('payment') === 'success') {
      notify('Pago procesado. Tu empresa se está creando.', 'success');
    }
  }, [notify, searchParams]);

  if (session.isLoading) return null;
  if (!session.data?.authenticated) {
    return <Navigate to="/account/login" replace />;
  }

  const current = status.data;
  const isDone = current?.status === 'completed' || current?.status === 'failed';
  const isSuccess = current?.status === 'completed';
  const isFailed = current?.status === 'failed';

  const statusTitle = isSuccess
    ? '¡Tu empresa está lista!'
    : isFailed
      ? 'Hubo un problema'
      : current?.status === 'running'
        ? 'Creando base de datos...'
        : 'Preparando tu empresa...';

  return (
    <AccountPortalLayout
      variant="app"
      showLogout
      backTo={{ label: 'Mis empresas', href: '/account/dashboard' }}
      title="Creando tu empresa"
    >
      <div className="ap-status">
        {!isDone ? <div className="ap-status__spinner" aria-hidden="true" /> : null}
        {isSuccess ? <div className="ap-status__icon ap-status__icon--success">✓</div> : null}
        {isFailed ? <div className="ap-status__icon ap-status__icon--error">×</div> : null}

        <h2>{statusTitle}</h2>

        <p>
          {isSuccess && current?.tenantUrl ? (
            <>
              <strong>{current.tenantName}</strong> está lista en{' '}
              <a href={current.tenantUrl} target="_blank" rel="noopener noreferrer">
                {current.tenantUrl}
              </a>
            </>
          ) : isFailed ? (
            <>No se pudo crear <strong>{current?.tenantName}</strong>.</>
          ) : (
            <>
              Estamos creando <strong>{current?.tenantName ?? 'tu empresa'}</strong>
              {current?.domain ? <> en <strong>{current.domain}</strong></> : null}.
              <br />
              Esto puede tomar unos segundos.
            </>
          )}
        </p>

        {current?.error ? (
          <p className="ap-hint ap-hint--error" style={{ marginTop: 12 }}>
            {current.error}
          </p>
        ) : null}

        <div className="ap-status__actions">
          <Link to="/account/dashboard">
            <Button type="button" variant="secondary">
              Ir al dashboard
            </Button>
          </Link>
          {current?.tenantUrl ? (
            <Button
              type="button"
              variant="primary"
              onClick={() => window.open(current.tenantUrl, '_blank', 'noopener,noreferrer')}
            >
              Abrir mi empresa
            </Button>
          ) : null}
        </div>
      </div>
    </AccountPortalLayout>
  );
}