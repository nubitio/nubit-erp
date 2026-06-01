import React, { useId } from 'react';
import './Toggle.scss';

export interface ToggleProps {
  /** Controlled checked value */
  checked: boolean;
  onChange: (checked: boolean) => void;
  /** Optional label rendered in a flex row alongside the toggle */
  label?: React.ReactNode;
  /** Size variant */
  size?: 'sm' | 'md';
  disabled?: boolean;
  /** Accessible name when no visible label is provided */
  'aria-label'?: string;
  className?: string;
}

/**
 * Accessible toggle switch. Always controlled — pass `checked` + `onChange`.
 * Renders a visually-hidden `<input type="checkbox">` with a custom track.
 *
 * With `label`: wraps in `.app-toggle-row` for a label ↔ toggle layout.
 * Without `label`: renders bare `.app-toggle`.
 */
export const Toggle = ({
  checked,
  onChange,
  label,
  size = 'md',
  disabled = false,
  'aria-label': ariaLabel,
  className = '',
}: ToggleProps) => {
  const id = useId();
  const sizeClass = size === 'sm' ? ' app-toggle--sm' : '';

  const input = (
    <span className={`app-toggle${sizeClass}${className ? ` ${className}` : ''}`}>
      <input
        id={id}
        type="checkbox"
        checked={checked}
        disabled={disabled}
        aria-label={!label ? ariaLabel : undefined}
        onChange={(e) => onChange(e.target.checked)}
      />
      <span className="app-toggle__track" aria-hidden="true" />
    </span>
  );

  if (!label) return input;

  return (
    <label htmlFor={id} className="app-toggle-row">
      <span>{label}</span>
      {input}
    </label>
  );
};
