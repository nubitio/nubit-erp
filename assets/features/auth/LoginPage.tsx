import { useMemo, useState } from 'react';

import '@assets/styles.scss';
import '@theme/theme';
import './LoginPage.scss';
import { LoginForm } from './LoginForm';

const NUBIT_ICON = '/assets/images/nubit-icon.png';

const BRAND_FEATURES = [
  {
    icon: 'ph-shopping-cart',
    title: 'POS y facturación electrónica',
    description: 'Ventas, comprobantes SUNAT y cobranzas',
  },
  {
    icon: 'ph-package',
    title: 'Inventario, compras y tesorería',
    description: 'Stock, proveedores, caja y bancos',
  },
  {
    icon: 'ph-calculator',
    title: 'Contabilidad y planilla',
    description: 'Libros contables y pagos al personal',
  },
] as const;

export const LoginPage = () => {
  const [brandLogoFailed, setBrandLogoFailed] = useState(false);
  const [mobileLogoFailed, setMobileLogoFailed] = useState(false);

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
              <span className="nubit-brand nubit-brand--on-dark">
                <img src={NUBIT_ICON} alt="" />
                <span>Nubit ERP</span>
              </span>
            )}
          </div>

          <div className="brand-main">
            <div className="brand-text">
              <h1>Gestiona tu negocio en un solo lugar.</h1>
              <p>
                Vende, factura a SUNAT, controla stock, compras, finanzas y planilla en un solo
                sistema.
              </p>
            </div>

            <ul className="brand-features">
              {BRAND_FEATURES.map((feature) => (
                <li key={feature.title}>
                  <span className="brand-features__icon">
                    <i className={`ph ${feature.icon}`} aria-hidden="true" />
                  </span>
                  <span>
                    <strong>{feature.title}</strong>
                    <small>{feature.description}</small>
                  </span>
                </li>
              ))}
            </ul>
          </div>

          <div className="brand-footer">
            <p className="brand-note">
              <i className="ph ph-shield-check" aria-hidden="true" />
              Nunca te pediremos tu contraseña ni códigos por WhatsApp o teléfono.
            </p>
            <p className="brand-legal">
              <span>Nubit Labs · Arequipa, Perú</span>
              {window.app_version && window.app_version !== 'dev' && (
                <span className="brand-version">{window.app_version}</span>
              )}
            </p>
          </div>
        </div>
      </aside>

      {/* ── Form panel ───────────────────────────────── */}
      <main className="login-form-panel">
        {/* mobile-only logo */}
        <div className="mobile-logo">
          {hasCustomMobileLogo ? (
            <img src={window.app_logo} alt="Logo" onError={() => setMobileLogoFailed(true)} />
          ) : (
            <span className="nubit-brand nubit-brand--mobile">
              <img src={NUBIT_ICON} alt="" />
              <span>Nubit ERP</span>
            </span>
          )}
        </div>

        <div className="form-card form-card--plain">
          <header className="form-header">
            <h2>Ingresa a tu cuenta</h2>
            <p>Usa tu usuario y contraseña del ERP.</p>
          </header>

          <LoginForm />
        </div>
      </main>
    </div>
  );
};
