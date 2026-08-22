import type { ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { Button } from '@nubitio/ui';
import { useNavigate } from 'react-router-dom';
import { accountPortalClient } from '../api/accountPortalClient';
import { useInvalidateAccountSession } from '../hooks/useAccountSession';
import { AccountPortalBrand } from './AccountPortalBrand';
import { FunnelSteps } from './FunnelSteps';

type LayoutVariant = 'auth' | 'app';

interface AccountPortalLayoutProps {
  children: ReactNode;
  variant?: LayoutVariant;
  title?: string;
  subtitle?: string;
  showLogout?: boolean;
  headerAction?: ReactNode;
  wide?: boolean;
  funnelStep?: 1 | 2 | 3 | 4;
  brandHeadline?: string;
  brandDescription?: string;
  backTo?: { label: string; href: string };
}

const NUBIT_ICON = '/assets/images/nubit-icon.png';

export function AccountPortalLayout({
  children,
  variant = 'auth',
  title,
  subtitle,
  showLogout = false,
  headerAction,
  wide = false,
  funnelStep,
  brandHeadline,
  brandDescription,
  backTo,
}: AccountPortalLayoutProps) {
  const navigate = useNavigate();
  const invalidateSession = useInvalidateAccountSession();

  const logout = async () => {
    await accountPortalClient.logout();
    await invalidateSession();
    navigate('/account/login', { replace: true });
  };

  if (variant === 'auth') {
    return (
      <div className="ap-root ap-root--auth">
        <AccountPortalBrand headline={brandHeadline} description={brandDescription} />
        <main className="ap-auth-panel">
          <div className="ap-auth-panel__mobile-logo">
            <img src={NUBIT_ICON} alt="" />
            <span>Nubit ERP</span>
          </div>
          <section className="ap-auth-card" aria-label="Portal de cuenta Nubit ERP">
            {funnelStep ? (
              <div className="ap-auth-card__funnel">
                <FunnelSteps current={funnelStep} />
              </div>
            ) : null}
            {title ? (
              <header className="ap-auth-card__header">
                <h1>{title}</h1>
                {subtitle ? <p>{subtitle}</p> : null}
              </header>
            ) : null}
            {children}
          </section>
        </main>
      </div>
    );
  }

  return (
    <div className="ap-root ap-root--app">
      <header className="ap-shell">
        <div className="ap-shell__inner">
          <Link to="/account/dashboard" className="ap-shell__brand">
            <img src={NUBIT_ICON} className="ap-shell__logo" alt="" />
            <span>Nubit ERP</span>
          </Link>

          <div className="ap-shell__actions">
            {headerAction}
            {showLogout ? (
              <Button type="button" variant="ghost" size="sm" onClick={logout}>
                Cerrar sesión
              </Button>
            ) : null}
          </div>
        </div>
      </header>

      <main className={`ap-main${wide ? ' ap-main--wide' : ''}`}>
        {backTo ? (
          <Link to={backTo.href} className="ap-back-link">
            <i className="ph ph-arrow-left" aria-hidden="true" />
            {backTo.label}
          </Link>
        ) : null}

        {(title || subtitle) && (
          <header className="ap-page-header">
            {title ? <h1>{title}</h1> : null}
            {subtitle ? <p>{subtitle}</p> : null}
          </header>
        )}

        {children}
      </main>
    </div>
  );
}
