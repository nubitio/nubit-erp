import type { ReactNode } from 'react';

interface CpSectionProps {
  title: string;
  children: ReactNode;
  className?: string;
}

export const CpSection = ({ title, children, className }: CpSectionProps) => (
  <section className={['cp-section', className].filter(Boolean).join(' ')}>
    <h2 className="cp-section__title">{title}</h2>
    {children}
  </section>
);