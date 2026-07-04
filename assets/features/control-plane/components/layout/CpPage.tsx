import type { ReactNode } from 'react';

interface CpPageProps {
  children: ReactNode;
  className?: string;
  /** Stretch to fill the admin shell content area (flex column). */
  fill?: boolean;
  /** Remove max-width cap for wide data views. */
  wide?: boolean;
}

export const CpPage = ({ children, className, fill, wide }: CpPageProps) => (
  <div
    className={[
      'cp-page',
      fill ? 'cp-page--fill' : '',
      wide ? 'cp-page--wide' : '',
      className,
    ]
      .filter(Boolean)
      .join(' ')}
  >
    {children}
  </div>
);