import React, { Component, ErrorInfo, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, EmptyState } from '@nubitio/ui';

type ErrorBoundaryProps = {
  children: ReactNode;
  /** Optional custom fallback UI; overrides the default error screen.
   *  Can be a static ReactNode or a render function receiving the caught error. */
  fallback?: ReactNode | ((error: Error | null) => ReactNode);
  /**
   * When this value changes the error state is reset, allowing recovery after
   * a route or context change (e.g. pass `useLocation().pathname` from the
   * parent router component).
   */
  resetKey?: string | number;
};

type ErrorBoundaryState = {
  hasError: boolean;
  error: Error | null;
};

/** Functional fallback component so we can use the `useTranslation` hook. */
function ErrorFallback({ error, onReload }: { error: Error | null; onReload: () => void }) {
  const { t } = useTranslation();

  return (
    <EmptyState
      variant="danger"
      size="lg"
      fill
      title={t('common.error')}
      description="Ocurrió un error inesperado. Por favor, recarga la página para continuar."
      action={(
        <>
          <Button variant="primary" onClick={onReload}>
            {t('common.retry')}
          </Button>
          {import.meta.env.DEV && error && (
            <details style={{ textAlign: 'left', maxWidth: '40rem', marginTop: 'var(--space-3)' }}>
              <summary style={{ cursor: 'pointer', fontWeight: 'var(--font-weight-semibold)', color: 'var(--text-secondary)', fontSize: 'var(--font-size-xs)' }}>
                Detalles del error (dev)
              </summary>
              <pre style={{ margin: 'var(--space-2) 0 0', overflow: 'auto', fontSize: 'var(--font-size-xs)', color: 'var(--error-color)', whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>
                {error.toString()}
                {'\n\n'}
                {error.stack}
              </pre>
            </details>
          )}
        </>
      )}
    />
  );
}

export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  constructor(props: ErrorBoundaryProps) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    // Log to the browser console so developers see the full stack in DevTools.
    console.error('[ErrorBoundary] Uncaught render error:', error, info.componentStack);
  }

  /**
   * Reset the boundary whenever the consumer changes the `resetKey` prop.
   * This mirrors the pattern recommended by the React docs for key-based reset.
   */
  componentDidUpdate(prevProps: ErrorBoundaryProps): void {
    if (this.state.hasError && prevProps.resetKey !== this.props.resetKey) {
      this.setState({ hasError: false, error: null });
    }
  }

  private handleReload = (): void => {
    window.location.reload();
  };

  render(): ReactNode {
    if (!this.state.hasError) {
      return this.props.children;
    }

    // Consumer-supplied fallback takes precedence.
    if (this.props.fallback !== undefined) {
      return typeof this.props.fallback === 'function'
        ? this.props.fallback(this.state.error)
        : this.props.fallback;
    }

    return <ErrorFallback error={this.state.error} onReload={this.handleReload} />;
  }
}
