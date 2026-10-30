import { MOVEMENT_TYPES, movementTone } from './kardexShared';

interface KardexMovementTypeCellProps {
  value: unknown;
  /** Document reference (e.g. F001-00000004); shown under the movement type. */
  reference?: string;
}

export function KardexMovementTypeCell({ value, reference }: KardexMovementTypeCellProps) {
  if (!value) return <span className="kardex-movement-type">—</span>;

  const type = String(value);
  const tone = movementTone(type);
  const entry = MOVEMENT_TYPES.find((item) => item.value === type);
  const hasReference = Boolean(reference) && reference !== '—';

  return (
    <span className="kardex-movement">
      <span className={`kardex-movement-type kardex-movement-type--${tone}`}>
        {entry?.icon && <i className={`ph ${entry.icon}`} aria-hidden="true" />}
        <span>{entry?.text ?? type}</span>
      </span>
      {hasReference && <span className="kardex-movement__reference">{reference}</span>}
    </span>
  );
}
