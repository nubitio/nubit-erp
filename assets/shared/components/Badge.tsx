import type { ReactNode } from 'react';

type BadgeVariant = 'primary' | 'secondary' | 'success' | 'danger' | 'warning' | 'info' | 'light' | 'dark';

interface BadgeProps {
  variant: BadgeVariant;
  outlined?: boolean;
  pill?: boolean;
  children: ReactNode;
}

export const Badge = ({ variant, outlined = true, pill = true, children }: BadgeProps) => {
  const cls = [
    'badge',
    outlined ? 'badge-outlined' : '',
    pill ? 'rounded-pill' : '',
    `text-bg-${variant}`,
  ]
    .filter(Boolean)
    .join(' ');

  return <span className={cls}>{children}</span>;
};
