import { useState } from 'react';
import type { ReactNode } from 'react';
import { AppDropdown, TextField, Toggle } from '@nubitio/ui';
import { SettingsRow } from './SettingsRow';

export interface ChoiceOption {
  value: string;
  label: string;
  /** A line under the label in the list; the field itself shows only the label. */
  description?: string;
}

/** An on/off setting: it is applied as soon as the switch is flipped. */
export const ToggleRow = ({
  label,
  description,
  checked,
  disabled,
  onChange,
}: {
  label: string;
  description?: ReactNode;
  checked: boolean;
  disabled?: boolean;
  onChange: (checked: boolean) => void;
}) => (
  <SettingsRow label={label} description={description}>
    <Toggle checked={checked} disabled={disabled} onChange={onChange} aria-label={label} />
  </SettingsRow>
);

/** One choice among a few: applied as soon as it is picked. */
export const SelectRow = ({
  id,
  label,
  description,
  value,
  options,
  disabled,
  onChange,
}: {
  id: string;
  label: string;
  description?: ReactNode;
  value: string;
  options: ChoiceOption[];
  disabled?: boolean;
  onChange: (value: string) => void;
}) => {
  // A stored value the list no longer offers is still shown, instead of silently reading as another.
  const choices = options.some((option) => option.value === value)
    ? options
    : [{ value, label: value }, ...options];
  return (
    <SettingsRow label={label} description={description} htmlFor={id}>
      <AppDropdown
        id={id}
        ariaLabel={label}
        value={value}
        options={choices}
        disabled={disabled}
        variant="form"
        showFieldLabel={false}
        menuWidth="content"
        onChange={onChange}
      />
    </SettingsRow>
  );
};

/**
 * A typed value saved when the field is left (or on Enter); Escape puts the saved value back.
 * Render it with a `key` that includes the saved value, so it restarts when the value changes.
 */
export const ValueRow = ({
  id,
  label,
  description,
  value,
  type = 'text',
  min,
  max,
  step,
  disabled,
  validate,
  onSave,
}: {
  id: string;
  label: string;
  description?: ReactNode;
  value: string;
  type?: 'text' | 'number';
  min?: number;
  max?: number;
  step?: number | 'any';
  disabled?: boolean;
  /** The message to show instead of saving, or `null` when the value is fine. */
  validate?: (value: string) => string | null;
  /** Saves the value; it reports its own failure. */
  onSave: (value: string) => Promise<void>;
}) => {
  const [draft, setDraft] = useState(value);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const commit = async () => {
    const next = draft.trim();
    if (next === value) {
      setError(null);
      return;
    }
    const problem = validate?.(next) ?? null;
    if (problem) {
      setError(problem);
      return;
    }
    setError(null);
    setSaving(true);
    try {
      await onSave(next);
    } catch {
      setDraft(value);
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <SettingsRow label={label} description={description} htmlFor={id}>
        <TextField
          id={id}
          type={type}
          min={min}
          max={max}
          step={step}
          value={draft}
          disabled={disabled || saving}
          invalid={error !== null}
          onChange={(event) => setDraft(event.target.value)}
          onBlur={() => void commit()}
          onKeyDown={(event) => {
            if (event.key === 'Enter') event.currentTarget.blur();
            if (event.key === 'Escape') {
              setDraft(value);
              setError(null);
            }
          }}
        />
      </SettingsRow>
      {error && (
        <p className="settings-error" role="alert">
          {error}
        </p>
      )}
    </>
  );
};
