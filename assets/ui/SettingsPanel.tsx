import React from 'react';
import { useDensity } from './theme/DensityProvider';
import { useAccentColor } from './theme/useAccentColor';
import './SettingsPanel.scss';

export interface SettingsPanelProps {
  onClose?: () => void;
}

export const SettingsPanel = ({ onClose: _onClose }: SettingsPanelProps) => {
  const { density, toggleDensity } = useDensity();
  const { accent, changeAccent, presets } = useAccentColor();

  return (
    <div className="settings-panel" role="menu" aria-label="Display settings">
      {/* ── Accent color ─────────────────────────────── */}
      <p className="settings-panel__heading">Color de acento</p>
      <div className="settings-panel__swatches" role="group" aria-label="Colores de acento">
        {presets.map((preset) => (
          <button
            key={preset.value}
            className="settings-panel__swatch"
            type="button"
            aria-label={preset.label}
            aria-pressed={accent === preset.value}
            title={preset.label}
            style={{ background: preset.value, color: preset.value }}
            onClick={() => changeAccent(preset.value)}
          />
        ))}
      </div>

      <hr className="settings-panel__divider" />

      {/* ── Density ──────────────────────────────────── */}
      <p className="settings-panel__heading">Densidad</p>
      <div className="settings-panel__density" role="group" aria-label="Densidad de interfaz">
        <button
          className="settings-panel__density-btn"
          type="button"
          aria-pressed={density === 'normal'}
          onClick={() => density !== 'normal' && toggleDensity()}
        >
          <i className="ph ph-rows" aria-hidden="true" />
          Normal
        </button>
        <button
          className="settings-panel__density-btn"
          type="button"
          aria-pressed={density === 'compact'}
          onClick={() => density !== 'compact' && toggleDensity()}
        >
          <i className="ph ph-rows-plus-bottom" aria-hidden="true" />
          Compact
        </button>
      </div>
    </div>
  );
};
