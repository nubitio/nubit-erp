import type { ReactNode } from 'react';

/** A titled block of rows, e.g. "Dónde has iniciado sesión". */
export const SettingsGroup = ({
  title,
  action,
  children,
}: {
  title?: string;
  /** Sits opposite the title (a button for the whole group). */
  action?: ReactNode;
  children: ReactNode;
}) => (
  <div className="settings-group">
    {(title || action) && (
      <div className="settings-group__header">
        {title && <h2 className="settings-group__title">{title}</h2>}
        {action}
      </div>
    )}
    <div className="settings-rows">{children}</div>
  </div>
);

/** What the setting is on the left (with a line of explanation), the control on the right. */
export const SettingsRow = ({
  label,
  description,
  htmlFor,
  children,
}: {
  label: string;
  description?: ReactNode;
  /** Id of the control, so the label names it. */
  htmlFor?: string;
  children?: ReactNode;
}) => (
  <div className="settings-row">
    <div className="settings-row__text">
      <label className="settings-row__label" htmlFor={htmlFor}>
        {label}
      </label>
      {description && <p className="settings-row__description">{description}</p>}
    </div>
    {children && <div className="settings-row__control">{children}</div>}
  </div>
);
