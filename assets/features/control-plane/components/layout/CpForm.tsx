import type { FormHTMLAttributes, ReactNode } from 'react';

type CpFormVariant = 'stack' | 'grid' | 'inline' | 'filter';

interface CpFormProps extends FormHTMLAttributes<HTMLFormElement> {
  children: ReactNode;
  variant?: CpFormVariant;
}

export const CpForm = ({ children, variant = 'stack', className, ...props }: CpFormProps) => (
  <form
    className={[
      'cp-form',
      variant !== 'stack' ? `cp-form--${variant}` : '',
      className,
    ]
      .filter(Boolean)
      .join(' ')}
    {...props}
  >
    {children}
  </form>
);