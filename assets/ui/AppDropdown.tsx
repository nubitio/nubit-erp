import { useEffect, useId, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import './AppDropdown.scss';

export interface AppDropdownOption {
  value: string;
  label: string;
  disabled?: boolean;
}

export type AppDropdownVariant = 'form' | 'toolbar' | 'compact';

export interface AppDropdownProps {
  id?: string;
  label?: ReactNode;
  icon?: string;
  value: string;
  options: AppDropdownOption[];
  onChange: (value: string) => void;
  disabled?: boolean;
  placeholder?: string;
  variant?: AppDropdownVariant;
  className?: string;
  /** When true, wraps the control in a form-field label (form variant only). */
  showFieldLabel?: boolean;
  error?: ReactNode;
  helpText?: ReactNode;
}

function cx(...values: Array<string | false | null | undefined>) {
  return values.filter(Boolean).join(' ');
}

function normalizeIcon(icon?: string): string | undefined {
  if (!icon) return undefined;
  if (icon.startsWith('ph ')) return icon;
  if (icon.startsWith('ph-')) return `ph ${icon}`;
  return `ph ph-${icon}`;
}

export function AppDropdown({
  id: idProp,
  label,
  icon,
  value,
  options,
  onChange,
  disabled = false,
  placeholder = 'Seleccionar…',
  variant = 'form',
  className,
  showFieldLabel = variant === 'form',
  error,
  helpText,
}: AppDropdownProps) {
  const generatedId = useId();
  const id = idProp ?? generatedId;
  const [open, setOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const selected = options.find((option) => option.value === value);

  useEffect(() => {
    if (!open) return;
    const handler = (event: MouseEvent) => {
      if (!containerRef.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  const control = (
    <div
      ref={containerRef}
      className={cx(
        'app-dropdown',
        `app-dropdown--${variant}`,
        open && 'app-dropdown--open',
        disabled && 'app-dropdown--disabled',
        className,
      )}
      role="group"
      aria-labelledby={label ? `${id}-label` : undefined}
    >
      {variant === 'toolbar' && label && (
        <span className="app-dropdown__toolbar-label" id={`${id}-label`}>
          {icon && <i className={normalizeIcon(icon)} aria-hidden="true" />}
          <span>{label}</span>
        </span>
      )}
      <button
        type="button"
        className="app-dropdown__trigger"
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-labelledby={label ? `${id}-label ${id}-value` : `${id}-value`}
        disabled={disabled}
        onClick={() => setOpen((current) => !current)}
      >
        <span className="app-dropdown__value" id={`${id}-value`}>
          {selected?.label ?? placeholder}
        </span>
        <i className="ph ph-caret-down app-dropdown__caret" aria-hidden="true" />
      </button>
      {open && !disabled && (
        <ul className="app-dropdown__menu" role="listbox" aria-labelledby={label ? `${id}-label` : undefined}>
          {options.map((option) => {
            const isActive = option.value === value;
            return (
              <li key={option.value} role="presentation">
                <button
                  type="button"
                  role="option"
                  aria-selected={isActive}
                  disabled={option.disabled}
                  className={cx(
                    'app-dropdown__option',
                    isActive && 'app-dropdown__option--active',
                  )}
                  onClick={() => {
                    if (option.disabled) return;
                    onChange(option.value);
                    setOpen(false);
                  }}
                >
                  {option.label}
                </button>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );

  if (!showFieldLabel || variant === 'toolbar') {
    return control;
  }

  return (
    <label className={cx('app-form-field', !!error && 'app-form-field--error')} htmlFor={id}>
      {label && <span className="app-form-field__label" id={`${id}-label`}>{label}</span>}
      {control}
      {error ? (
        <span className="app-form-field__error" role="alert">{error}</span>
      ) : helpText ? (
        <span className="app-form-field__help">{helpText}</span>
      ) : null}
    </label>
  );
}