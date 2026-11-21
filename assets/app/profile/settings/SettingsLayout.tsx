import type { ReactNode } from 'react';
import { Link } from 'react-router-dom';

import './settings.scss';

export interface SettingsNavItem {
  key: string;
  label: string;
  /** Phosphor icon class (`ph-…`). */
  icon: string;
  /** Set when the item leaves the panel for another page (shown with an arrow). */
  href?: string;
}

export interface SettingsNavGroup {
  key: string;
  title?: string;
  items: SettingsNavItem[];
}

interface SettingsLayoutProps {
  title: string;
  groups: SettingsNavGroup[];
  activeKey: string;
  onSelect: (key: string) => void;
  children: ReactNode;
}

/** Section list on the left, the chosen section on the right. Fills its container; the list is a scrolling strip on narrow screens. */
export const SettingsLayout = ({
  title,
  groups,
  activeKey,
  onSelect,
  children,
}: SettingsLayoutProps) => (
  <div className="settings-shell">
    <nav className="settings-nav" aria-label="Secciones de ajustes">
      {groups.map((group) => (
        <div key={group.key} className="settings-nav__group">
          {group.title && <p className="settings-nav__title">{group.title}</p>}
          {group.items.map((item) =>
            item.href ? (
              <Link key={item.key} to={item.href} className="settings-nav__item">
                <i className={`ph ${item.icon}`} aria-hidden="true" />
                <span>{item.label}</span>
                <i className="ph ph-arrow-up-right settings-nav__out" aria-hidden="true" />
              </Link>
            ) : (
              <button
                key={item.key}
                type="button"
                className="settings-nav__item"
                aria-current={item.key === activeKey ? 'page' : undefined}
                onClick={() => onSelect(item.key)}
              >
                <i className={`ph ${item.icon}`} aria-hidden="true" />
                <span>{item.label}</span>
              </button>
            ),
          )}
        </div>
      ))}
    </nav>
    <section className="settings-content" aria-labelledby="settings-title">
      <h1 id="settings-title" className="settings-content__title">
        {title}
      </h1>
      {children}
    </section>
  </div>
);
