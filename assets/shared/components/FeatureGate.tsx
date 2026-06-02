import React, { useRef, useState } from 'react';
import { Badge, IconButton } from '@nubit/ui';
import { useFeature } from '../hooks/useFeature';
import './FeatureGate.scss';

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

/** URL to the eFact pricing page on the marketing site. */
const PRICING_URL = 'https://efact.pe/precios';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface FeatureGateProps {
  /** The feature key to check (e.g. 'excel_export'). */
  featureKey: string;

  /** The element(s) to gate. */
  children: React.ReactNode;

  /**
   * Visual behavior when the feature is disabled:
   * - 'lock'    — wraps children with disabled styling + tooltip (type B)
   * - 'upgrade' — shows children with a plan badge; click reveals upgrade prompt (type C)
   *
   * @default 'lock'
   */
  behavior?: 'lock' | 'upgrade';

  /**
   * Label for the plan badge shown in 'upgrade' mode (e.g. 'Pro', 'Basic').
   * @default 'Pro'
   */
  planBadge?: string;

  /**
   * Message shown in the upgrade prompt.
   * Defaults to a generic "upgrade your plan" message.
   */
  upgradeMessage?: string;

  /**
   * Tooltip text shown on hover in 'lock' mode.
   * @default 'Esta función no está disponible en tu plan actual.'
   */
  lockTooltip?: string;
}

// ---------------------------------------------------------------------------
// FeatureGate
// ---------------------------------------------------------------------------

/**
 * Gates a UI element behind a feature flag.
 *
 * When the feature IS enabled → renders children as-is, zero overhead.
 * When the feature IS NOT enabled:
 *   - behavior='lock'    → disabled overlay + tooltip (type B)
 *   - behavior='upgrade' → plan badge + upgrade prompt on click (type C)
 *
 * @example — type B (most features)
 *   <FeatureGate featureKey="excel_export">
 *     <ExportButton />
 *   </FeatureGate>
 *
 * @example — type C (high-value features)
 *   <FeatureGate featureKey="api_access" behavior="upgrade" planBadge="Pro">
 *     <ApiSettingsPanel />
 *   </FeatureGate>
 */
export function FeatureGate({
  featureKey,
  children,
  behavior = 'lock',
  planBadge = 'Pro',
  upgradeMessage,
  lockTooltip = 'Esta función no está disponible en tu plan actual.',
}: FeatureGateProps) {
  const hasFeature = useFeature(featureKey);

  // Feature enabled → render children transparently
  if (hasFeature) {
    return <div className="feature-gate">{children}</div>;
  }

  if (behavior === 'upgrade') {
    return (
      <UpgradeGate planBadge={planBadge} upgradeMessage={upgradeMessage}>
        {children}
      </UpgradeGate>
    );
  }

  // behavior === 'lock'
  return (
    <div
      className="feature-gate--locked"
      title={lockTooltip}
      aria-label={lockTooltip}
      role="presentation"
    >
      {children}
    </div>
  );
}

// ---------------------------------------------------------------------------
// UpgradeGate — internal component for 'upgrade' behavior (type C)
// ---------------------------------------------------------------------------

interface UpgradeGateProps {
  planBadge: string;
  upgradeMessage?: string;
  children: React.ReactNode;
}

function UpgradeGate({ planBadge, upgradeMessage, children }: UpgradeGateProps) {
  const [open, setOpen] = useState(false);
  const badgeRef = useRef<HTMLSpanElement>(null);

  const message =
    upgradeMessage ??
    `Esta función requiere el plan ${planBadge}. Actualiza tu plan para desbloquearla.`;

  const handleBadgeClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    setOpen((prev) => !prev);
  };

  const handleClose = (e: React.MouseEvent) => {
    e.stopPropagation();
    setOpen(false);
  };

  // Close prompt when clicking outside
  const handleBlur = (e: React.FocusEvent) => {
    if (!e.currentTarget.contains(e.relatedTarget as Node | null)) {
      setOpen(false);
    }
  };

  return (
    <div className="feature-gate--upgrade" onBlur={handleBlur}>
      {children}

      {/* Badge acts as a trigger — rendered as a button via onClick */}
      <span
        ref={badgeRef as React.RefObject<HTMLSpanElement>}
        role="button"
        tabIndex={0}
        className="feature-gate__badge"
        aria-expanded={open}
        aria-label={`Requiere plan ${planBadge}`}
        onClick={handleBadgeClick}
        onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleBadgeClick(e as unknown as React.MouseEvent); } }}
      >
        <Badge variant="primary" size="sm" pill>
          🔒 {planBadge}
        </Badge>
      </span>

      {open && (
        <div className="feature-gate__prompt" role="dialog" aria-label="Información de upgrade">
          <IconButton
            icon="ph ph-x"
            label="Cerrar"
            onClick={handleClose}
            className="feature-gate__prompt__close"
          />
          <p className="feature-gate__prompt__message">{message}</p>
          <a
            href={PRICING_URL}
            target="_blank"
            rel="noopener noreferrer"
            className="feature-gate__prompt__link"
          >
            Ver planes →
          </a>
        </div>
      )}
    </div>
  );
}
