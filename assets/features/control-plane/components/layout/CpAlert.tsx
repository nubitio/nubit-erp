import type { ReactNode } from 'react';

type CpAlertTone = 'danger' | 'warning' | 'info';

interface CpAlertProps {
  tone?: CpAlertTone;
  children: ReactNode;
  className?: string;
}

const icons: Record<CpAlertTone, string> = {
  danger: 'ph-warning-circle',
  warning: 'ph-warning',
  info: 'ph-info',
};

export const CpAlert = ({ tone = 'info', children, className }: CpAlertProps) => (
  <div
    className={['cp-alert', `cp-alert--${tone}`, className].filter(Boolean).join(' ')}
    role="alert"
  >
    <i className={`ph ${icons[tone]}`} aria-hidden />
    <span>{children}</span>
  </div>
);