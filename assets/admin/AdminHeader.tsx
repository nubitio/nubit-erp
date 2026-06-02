import React, { useEffect, useRef, useState } from 'react';
import { IconButton } from '@nubit/ui';

export interface AdminHeaderAction {
  id: string;
  icon: string;
  label: string;
  badge?: number;
  renderPanel: (props: { close: () => void }) => React.ReactNode;
}

export interface AdminHeaderProps {
  title?: string;
  menuToggleEnabled?: boolean;
  toggleMenu?: (e: React.MouseEvent<HTMLButtonElement>) => void;
  className?: string;
  actions?: AdminHeaderAction[];
  renderUserMenu?: (props: { close: () => void }) => React.ReactNode;
  renderThemeSwitcher?: () => React.ReactNode;
}

export const AdminHeader = ({
  title,
  menuToggleEnabled,
  toggleMenu,
  className,
  actions = [],
  renderUserMenu,
  renderThemeSwitcher,
}: AdminHeaderProps) => {
  const [openPanelId, setOpenPanelId] = useState<string | null>(null);
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const panelRefs = useRef<Map<string, HTMLDivElement | null>>(new Map());
  const userMenuRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    if (!openPanelId && !userMenuOpen) return;

    const handlePointerDown = (event: PointerEvent) => {
      const target = event.target as Node;
      if (openPanelId) {
        const panelRef = panelRefs.current.get(openPanelId);
        if (!panelRef?.contains(target)) setOpenPanelId(null);
      }
      if (userMenuOpen && !userMenuRef.current?.contains(target)) {
        setUserMenuOpen(false);
      }
    };
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpenPanelId(null);
        setUserMenuOpen(false);
      }
    };

    document.addEventListener('pointerdown', handlePointerDown);
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.removeEventListener('pointerdown', handlePointerDown);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [openPanelId, userMenuOpen]);

  return (
    <header className={['header-component', className].filter(Boolean).join(' ')}>
      <div className="header-toolbar" role="toolbar" aria-label="Main toolbar">
        <div className="header-toolbar__before">
          {menuToggleEnabled && (
            <IconButton
              className="menu-button"
              icon="ph ph-list"
              label="Toggle menu"
              onClick={toggleMenu}
            />
          )}
          {title && <div className="header-title">{title}</div>}
        </div>
        <div className="header-toolbar__after">
          {renderThemeSwitcher?.()}
          {actions.map((action) => {
            const isOpen = openPanelId === action.id;
            return (
              <div
                key={action.id}
                className="header-popover"
                ref={(el) => { panelRefs.current.set(action.id, el); }}
              >
                <div className="messages">
                  <IconButton
                    icon={action.icon}
                    label={action.label}
                    aria-expanded={isOpen}
                    onClick={() => setOpenPanelId(isOpen ? null : action.id)}
                  />
                  {!!action.badge && action.badge > 0 && (
                    <div className="app-badge" aria-label={`${action.badge} ${action.label}`}>
                      {action.badge > 99 ? '99+' : action.badge}
                    </div>
                  )}
                </div>
                {isOpen && (
                  <div
                    className="header-popover__panel"
                    role="dialog"
                    aria-label={action.label}
                  >
                    {action.renderPanel({ close: () => setOpenPanelId(null) })}
                  </div>
                )}
              </div>
            );
          })}
          {renderUserMenu && (
            <div className="header-popover" ref={userMenuRef}>
              <IconButton
                icon="ph ph-user-circle"
                label="User menu"
                aria-expanded={userMenuOpen}
                onClick={() => setUserMenuOpen((prev) => !prev)}
              />
              {userMenuOpen && (
                <div
                  className="header-popover__panel"
                  role="dialog"
                  aria-label="User menu"
                >
                  {renderUserMenu({ close: () => setUserMenuOpen(false) })}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </header>
  );
};
