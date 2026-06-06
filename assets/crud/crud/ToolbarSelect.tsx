import { useEffect, useRef, useState } from 'react';

export interface ToolbarSelectOption {
  value: string;
  label: string;
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

export interface ToolbarSelectProps {
  id: string;
  label: string;
  icon?: string;
  value: string;
  options: ToolbarSelectOption[];
  onChange: (value: string) => void;
  disabled?: boolean;
}

export function ToolbarSelect({
  id,
  label,
  icon = 'ph-funnel',
  value,
  options,
  onChange,
  disabled = false,
}: ToolbarSelectProps) {
  const [open, setOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const selected = options.find((option) => option.value === value) ?? options[0];

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

  return (
    <div
      ref={containerRef}
      className="native-datagrid__toolbar-select"
      role="group"
      aria-labelledby={`${id}-label`}
    >
      <span className="native-datagrid__toolbar-select-label" id={`${id}-label`}>
        {icon && <i className={normalizeIcon(icon)} aria-hidden="true" />}
        <span>{label}</span>
      </span>
      <button
        type="button"
        className={cx(
          'native-datagrid__toolbar-select-trigger',
          open && 'native-datagrid__toolbar-select-trigger--open',
        )}
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-labelledby={`${id}-label ${id}-value`}
        disabled={disabled}
        onClick={() => setOpen((current) => !current)}
      >
        <span className="native-datagrid__toolbar-select-value" id={`${id}-value`}>
          {selected?.label ?? ''}
        </span>
        <i className="ph ph-caret-down" aria-hidden="true" />
      </button>
      {open && !disabled && (
        <ul className="native-datagrid__toolbar-select-menu" role="listbox" aria-labelledby={`${id}-label`}>
          {options.map((option) => {
            const isActive = option.value === value;
            return (
              <li key={option.value} role="presentation">
                <button
                  type="button"
                  role="option"
                  aria-selected={isActive}
                  className={cx(
                    'native-datagrid__toolbar-select-option',
                    isActive && 'native-datagrid__toolbar-select-option--active',
                  )}
                  onClick={() => {
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
}