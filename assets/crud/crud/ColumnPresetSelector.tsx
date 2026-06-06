import type { ColumnPreset } from './ColumnPreset';

function cx(...values: Array<string | false | null | undefined>) {
  return values.filter(Boolean).join(' ');
}

function normalizeIcon(icon?: string): string | undefined {
  if (!icon) return undefined;
  if (icon.startsWith('ph ')) return icon;
  if (icon.startsWith('ph-')) return `ph ${icon}`;
  return `ph ph-${icon}`;
}

export interface ColumnPresetSelectorProps {
  resourceId: string;
  presets: ColumnPreset[];
  activePreset: string | null;
  onPresetChange: (key: string | null) => void;
  columnsLabel: string;
  allColumnsLabel: string;
}

export function ColumnPresetSelector({
  resourceId,
  presets,
  activePreset,
  onPresetChange,
  columnsLabel,
  allColumnsLabel,
}: ColumnPresetSelectorProps) {
  const labelId = `preset-label-${resourceId}`;

  return (
    <div
      className="native-datagrid__preset-control"
      role="group"
      aria-labelledby={labelId}
    >
      <span className="native-datagrid__preset-label" id={labelId}>
        <i className={normalizeIcon('ph-columns')} aria-hidden="true" />
        <span>{columnsLabel}</span>
      </span>
      <div className="native-datagrid__preset-segments">
        <button
          type="button"
          className={cx(
            'native-datagrid__preset-segment',
            activePreset === null && 'native-datagrid__preset-segment--active',
          )}
          onClick={() => onPresetChange(null)}
          aria-pressed={activePreset === null}
        >
          {allColumnsLabel}
        </button>
        {presets.map((preset) => (
          <button
            key={preset.key}
            type="button"
            className={cx(
              'native-datagrid__preset-segment',
              activePreset === preset.key && 'native-datagrid__preset-segment--active',
            )}
            onClick={() => onPresetChange(preset.key)}
            aria-pressed={activePreset === preset.key}
          >
            {preset.label}
          </button>
        ))}
      </div>
    </div>
  );
}