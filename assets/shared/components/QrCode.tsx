import { useMemo } from 'react';
import qrcode from 'qrcode-generator';

export type QrErrorLevel = 'L' | 'M' | 'Q' | 'H';

/** Modules of the QR as rows of booleans (true = dark). Encoded here, in the browser. */
export function qrMatrix(value: string, errorLevel: QrErrorLevel = 'M'): boolean[][] {
  // Type 0 = pick the smallest version that fits the data.
  const qr = qrcode(0, errorLevel);
  qr.addData(value);
  qr.make();
  const count = qr.getModuleCount();
  return Array.from({ length: count }, (_, row) =>
    Array.from({ length: count }, (_, column) => qr.isDark(row, column)),
  );
}

/** Quiet zone, in modules, that scanners need around the code. */
export const QR_QUIET_ZONE = 4;

/** One SVG path for all dark modules; horizontal runs are merged to keep it short. */
export function qrPath(matrix: boolean[][], margin = QR_QUIET_ZONE): string {
  const segments: string[] = [];
  matrix.forEach((row, y) => {
    let x = 0;
    while (x < row.length) {
      if (!row[x]) {
        x += 1;
        continue;
      }
      const start = x;
      while (x < row.length && row[x]) x += 1;
      segments.push(`M${start + margin} ${y + margin}h${x - start}v1h-${x - start}z`);
    }
  });
  return segments.join('');
}

interface QrCodeProps {
  value: string;
  /** Rendered size in px (the code is square). */
  size?: number;
  /** What the code is for, for assistive technology. The value itself is not announced. */
  label: string;
  className?: string;
}

/**
 * A QR code drawn as an inline SVG. Always black on white, whatever the theme: scanners expect dark
 * modules on a light ground, and the white quiet zone is part of the code.
 */
export const QrCode = ({ value, size = 176, label, className }: QrCodeProps) => {
  const matrix = useMemo(() => qrMatrix(value), [value]);
  const path = useMemo(() => qrPath(matrix), [matrix]);
  const side = matrix.length + QR_QUIET_ZONE * 2;

  return (
    <svg
      className={className}
      role="img"
      aria-label={label}
      width={size}
      height={size}
      viewBox={`0 0 ${side} ${side}`}
      shapeRendering="crispEdges"
    >
      <rect width={side} height={side} fill="#ffffff" />
      <path d={path} fill="#000000" />
    </svg>
  );
};
