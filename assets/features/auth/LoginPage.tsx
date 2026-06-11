import { useMemo, useState } from 'react';
import { useTheme } from '@nubitio/ui';

import '@assets/styles.scss';
import '@theme/theme';
import './LoginPage.scss';
import { LoginForm } from './LoginForm';

const LOGO_LIGHT = '/assets/images/efact-logo.svg';
const LOGO_DARK  = '/assets/images/efact-logo-dark.svg';

export const LoginPage = () => {
  const [brandLogoFailed, setBrandLogoFailed] = useState(false);
  const [mobileLogoFailed, setMobileLogoFailed] = useState(false);
  const { theme } = useTheme();

  const hasCustomLogo = useMemo(
    () => Boolean(window.app_logo) && !brandLogoFailed,
    [brandLogoFailed],
  );

  const hasCustomMobileLogo = useMemo(
    () => Boolean(window.app_logo) && !mobileLogoFailed,
    [mobileLogoFailed],
  );

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
            {hasCustomLogo ? (
              <img
                src={window.app_logo}
                className="brand-logo brand-logo--custom"
                alt="Logo"
                onError={() => setBrandLogoFailed(true)}
              />
            ) : (
              <img
                src={LOGO_DARK}
                className="brand-logo brand-logo--efact"
                alt="Efact"
              />
            )}
          </div>

          <div className="brand-illustration">
            <img src="/assets/images/illustration.svg" alt="" />
          </div>

          <div className="brand-text">
            <h1>
              Simplifica tu trabajo,
              <br />
              optimiza tu negocio.
            </h1>
            <p>La plataforma de facturación electrónica más intuitiva para tu empresa.</p>
          </div>

          <ul className="brand-features">
            <li>
              <i className="ph ph-check-circle" aria-hidden="true" />
              Facturación electrónica SUNAT
            </li>
            <li>
              <i className="ph ph-check-circle" aria-hidden="true" />
              POS integrado multilocal
            </li>
            <li>
              <i className="ph ph-check-circle" aria-hidden="true" />
              Inventario en tiempo real
            </li>
          </ul>

          {window.app_version && <span className="brand-version">{window.app_version}</span>}
        </div>
      </aside>

      {/* ── Form panel ───────────────────────────────── */}
      <main className="login-form-panel">
        {/* mobile-only logo */}
        <div className="mobile-logo">
          {hasCustomMobileLogo ? (
            <img src={window.app_logo} alt="Logo" onError={() => setMobileLogoFailed(true)} />
          ) : (
            <img
              src={theme === 'dark' ? LOGO_DARK : LOGO_LIGHT}
              className="efact-wordmark"
              alt="Efact"
            />
          )}
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
