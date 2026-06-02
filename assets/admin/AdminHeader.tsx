import React, { useState } from 'react';
import { Badge, IconButton, useFloatingPanel } from '@nubit/ui';

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

// ---------------------------------------------------------------------------
// ActionPopover — each header action owns its own floating panel state so
// click-outside logic is scoped to the action's container, not the whole header.
// ---------------------------------------------------------------------------
function ActionPopover({ action }: { action: AdminHeaderAction }) {
  const { open, toggle, setOpen, containerRef } = useFloatingPanel();

  return (
    <div
      className="header-popover"
      ref={containerRef}
    >
      <div className="messages">
        <IconButton
          icon={action.icon}
          label={action.label}
          aria-expanded={open}
          onClick={toggle}
        />
        {!!action.badge && action.badge > 0 && (
          <Badge
            variant="danger"
            size="sm"
            pill
            aria-label={`${action.badge} ${action.label}`}
          >
            {action.badge > 99 ? '99+' : action.badge}
          </Badge>
        )}
      </div>
      {open && (
        <div
          className="header-popover__panel"
          role="dialog"
          aria-label={action.label}
        >
          {action.renderPanel({ close: () => setOpen(false) })}
        </div>
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// UserMenuPopover — same pattern for the optional user menu slot.
// ---------------------------------------------------------------------------
function UserMenuPopover({
  renderUserMenu,
}: {
  renderUserMenu: (props: { close: () => void }) => React.ReactNode;
}) {
  const { open, toggle, setOpen, containerRef } = useFloatingPanel();

  return (
    <div className="header-popover" ref={containerRef}>
      <IconButton
        icon="ph ph-user-circle"
        label="User menu"
        aria-expanded={open}
        onClick={toggle}
      />
      {open && (
        <div
          className="header-popover__panel"
          role="dialog"
          aria-label="User menu"
        >
          {renderUserMenu({ close: () => setOpen(false) })}
        </div>
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// AdminHeader
// ---------------------------------------------------------------------------
export const AdminHeader = ({
  title,
  menuToggleEnabled,
  toggleMenu,
  className,
  actions = [],
  renderUserMenu,
  renderThemeSwitcher,
}: AdminHeaderProps) => (
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
        {actions.map((action) => (
          <ActionPopover key={action.id} action={action} />
        ))}
        {renderUserMenu && <UserMenuPopover renderUserMenu={renderUserMenu} />}
      </div>
    </div>
  </header>
);
