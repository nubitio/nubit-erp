import React, { Component, ErrorInfo, ReactNode } from 'react';

type ErrorBoundaryProps = {
  children: ReactNode;
  /** Optional custom fallback UI; overrides the default error screen. */
  fallback?: ReactNode;
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
      return this.props.fallback;
    }

    return (
      <div
        role="alert"
        aria-live="assertive"
        style={{
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          minHeight: '100vh',
          padding: '2rem',
          gap: '1rem',
          fontFamily: 'inherit',
          textAlign: 'center',
        }}
      >
        <h1 style={{ fontSize: '1.5rem', fontWeight: 700, margin: 0 }}>Algo salió mal</h1>
        <p style={{ color: '#6b7280', margin: 0 }}>
          Ocurrió un error inesperado. Por favor, recarga la página para continuar.
        </p>

        <button
          type="button"
          onClick={this.handleReload}
          style={{
            marginTop: '0.5rem',
            padding: '0.5rem 1.5rem',
            borderRadius: '0.375rem',
            border: 'none',
            background: '#1463ff',
            color: '#ffffff',
            fontWeight: 600,
            fontSize: '0.875rem',
            cursor: 'pointer',
          }}
        >
          Recargar página
        </button>

        {/* Show technical details only in dev mode to aid debugging. */}
        {import.meta.env.DEV && this.state.error && (
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
            <summary style={{ cursor: 'pointer', fontWeight: 600 }}>
              Detalles del error (dev)
            </summary>
            <pre style={{ margin: '0.5rem 0 0', overflow: 'auto' }}>
              {this.state.error.toString()}
              {'\n\n'}
              {this.state.error.stack}
            </pre>
          </details>
        )}
      </div>
    );
  }
}
