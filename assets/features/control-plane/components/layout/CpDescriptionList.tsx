import type { ReactNode } from 'react';

export interface CpDescriptionItem {
  term: string;
  value: ReactNode;
}

interface CpDescriptionListProps {
  items: CpDescriptionItem[];
  className?: string;
}

export const CpDescriptionList = ({ items, className }: CpDescriptionListProps) => (
  <dl className={['cp-description-list', className].filter(Boolean).join(' ')}>
    {items.map((item) => (
      <div key={item.term} className="cp-description-list__row">
        <dt>{item.term}</dt>
        <dd>{item.value}</dd>
      </div>
    ))}
  </dl>
);