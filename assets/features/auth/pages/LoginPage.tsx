import { useEffect } from 'react';
import { configureDevExtreme } from '@app/providers/devextreme';

import '@assets/styles.scss';
import '@theme/theme';
import './LoginPage.scss';
import { LoginForm } from './LoginForm';

export const LoginPage = () => {
  useEffect(() => {
    configureDevExtreme();
  }, []);

  return (
    <div id="login-root">
      {/* ── Brand panel ──────────────────────────────── */}
      <aside className="login-brand" aria-hidden="true">
        <div className="brand-blobs">
          <span className="blob blob-1" />
          <span className="blob blob-2" />
          <span className="blob blob-3" />
        </div>

        <div className="brand-content">
          <div className="brand-logo-wrap">
            <img src={window.app_logo} className="brand-logo" alt="Logo" />
          </div>

          <div className="brand-illustration">
            <img src="assets/images/illustration.svg" alt="" />
          </div>

          <div className="brand-text">
            <h1>Simplifica tu trabajo,<br />optimiza tu negocio.</h1>
            <p>La plataforma de facturación electrónica más intuitiva para tu empresa.</p>
          </div>

          {window.app_version && (
            <span className="brand-version">{window.app_version}</span>
          )}
        </div>
      </aside>

      {/* ── Form panel ───────────────────────────────── */}
      <main className="login-form-panel">
        {/* mobile-only logo */}
        <div className="mobile-logo">
          <img src={window.app_logo} alt="Logo" />
        </div>

        <div className="form-card">
          <header className="form-header">
            <h2>Bienvenido de nuevo</h2>
            <p>Ingresa tus credenciales para continuar</p>
          </header>

          <LoginForm />
        </div>
      </main>
    </div>
  );
};
