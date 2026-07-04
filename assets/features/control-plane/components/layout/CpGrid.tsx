import type { ReactNode } from 'react';

type CpGridCols = 1 | 2 | 3 | 'auto';

interface CpGridProps {
  children: ReactNode;
  cols?: CpGridCols;
  className?: string;
}

export const CpGrid = ({ children, cols = 2, className }: CpGridProps) => (
  <div className={['cp-grid', `cp-grid--${cols}`, className].filter(Boolean).join(' ')}>
    {children}
  </div>
);