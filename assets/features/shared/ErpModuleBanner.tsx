import type { ReactNode } from 'react';
import './ErpModuleBanner.scss';

type BannerTone = 'info' | 'warning';

interface ErpModuleBannerProps {
  tone?: BannerTone;
  children: ReactNode;
}

export function ErpModuleBanner({ tone = 'info', children }: ErpModuleBannerProps) {
  return (
    <div className={`erp-module-banner erp-module-banner--${tone}`} role="note">
      <i className={`ph ${tone === 'warning' ? 'ph-warning' : 'ph-info'}`} aria-hidden="true" />
      <p>{children}</p>
    </div>
  );
}
