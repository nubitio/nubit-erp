import { MOVEMENT_TYPES, movementTone } from './kardexShared';

interface KardexMovementTypeCellProps {
  value: unknown;
}

export function KardexMovementTypeCell({ value }: KardexMovementTypeCellProps) {
  if (!value) return <span className="kardex-movement-type">—</span>;

  const type = String(value);
  const tone = movementTone(type);
  const entry = MOVEMENT_TYPES.find((item) => item.value === type);

  return (
    <span className={`kardex-movement-type kardex-movement-type--${tone}`}>
      {entry?.icon && <i className={`ph ${entry.icon}`} aria-hidden="true" />}
      <span>{entry?.text ?? type}</span>
    </span>
  );
}