interface PieceCounterProps {
  pieces: number;
  productName: string;
  onChange: (next: number) => void;
}

/** +/- control for the number of pieces on a weighted line. Never goes below zero. */
export function PieceCounter({ pieces, productName, onChange }: PieceCounterProps) {
  return (
    <div className="pos-line__pieces" role="group" aria-label={`Unidades de ${productName}`}>
      <button
        type="button"
        disabled={pieces <= 0}
        onClick={() => onChange(pieces - 1)}
        aria-label={`Restar unidad de ${productName}`}
      >
        <i className="ph ph-minus" aria-hidden="true" />
      </button>
      <span className="pos-line__pieces-value" aria-live="polite">
        {pieces} und
      </span>
      <button
        type="button"
        onClick={() => onChange(pieces + 1)}
        aria-label={`Sumar unidad de ${productName}`}
      >
        <i className="ph ph-plus" aria-hidden="true" />
      </button>
    </div>
  );
}
