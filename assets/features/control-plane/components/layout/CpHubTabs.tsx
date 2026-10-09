import { Badge } from '@nubitio/ui';
import type { ReactNode } from 'react';

export interface CpHubTab<T extends string = string> {
  id: T;
  label: ReactNode;
  icon?: string;
  badge?: number;
  badgeVariant?: 'default' | 'warning';
}

interface CpHubTabsProps<T extends string> {
  tabs: CpHubTab<T>[];
  active: T;
  onChange: (tab: T) => void;
  ariaLabel: string;
}

const iconClass = (icon?: string) => {
  if (!icon) return undefined;
  return icon.includes(' ') ? icon : `ph ph-${icon}`;
};

export const CpHubTabs = <T extends string>({
  tabs,
  active,
  onChange,
  ariaLabel,
}: CpHubTabsProps<T>) => (
  <nav
    className="nb-feature-hub__tabs nb-feature-hub__tabs--underline cp-hub-tabs"
    aria-label={ariaLabel}
  >
    {tabs.map((tab) => (
      <button
        key={tab.id}
        type="button"
        role="tab"
        aria-selected={active === tab.id}
        className={`nb-feature-hub__tab${active === tab.id ? ' nb-feature-hub__tab--active' : ''}`}
        onClick={() => onChange(tab.id)}
      >
        {tab.icon && <i className={iconClass(tab.icon)} aria-hidden />}
        <span>{tab.label}</span>
        {tab.badge != null && tab.badge > 0 && (
          <Badge variant={tab.badgeVariant === 'warning' ? 'warning' : 'secondary'} size="sm" pill>
            {tab.badge}
          </Badge>
        )}
      </button>
    ))}
  </nav>
);
