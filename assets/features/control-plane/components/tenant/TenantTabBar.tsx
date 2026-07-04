import type { TenantTab } from '../../types';

const TABS: Array<{ id: TenantTab; label: string; icon: string }> = [
  { id: 'general', label: 'General', icon: 'bi-pencil-square' },
  { id: 'domains', label: 'Domains', icon: 'bi-globe' },
  { id: 'provisioning', label: 'Provisioning', icon: 'bi-rocket' },
  { id: 'usage', label: 'Usage', icon: 'bi-graph-up' },
  { id: 'subscription', label: 'Subscription', icon: 'bi-credit-card' },
  { id: 'backups', label: 'Backups', icon: 'bi-archive' },
  { id: 'certificates', label: 'Certificates', icon: 'bi-shield-lock' },
];

interface TenantTabBarProps {
  active: TenantTab;
  onChange: (tab: TenantTab) => void;
  badges?: Partial<Record<TenantTab, number>>;
}

export const TenantTabBar = ({ active, onChange, badges }: TenantTabBarProps) => (
  <nav className="cp-tabs" role="tablist">
    {TABS.map((tab) => (
      <button
        key={tab.id}
        type="button"
        role="tab"
        aria-selected={active === tab.id}
        className={`cp-tabs__item${active === tab.id ? ' cp-tabs__item--active' : ''}`}
        onClick={() => onChange(tab.id)}
      >
        <i className={`bi ${tab.icon}`} />
        <span>{tab.label}</span>
        {badges?.[tab.id] ? <span className="cp-tabs__badge">{badges[tab.id]}</span> : null}
      </button>
    ))}
  </nav>
);