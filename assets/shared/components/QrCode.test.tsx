import { cleanup, render, screen } from '@testing-library/react';
import jsQR from 'jsqr';
import { afterEach, describe, expect, it } from 'vitest';
import { QR_QUIET_ZONE, QrCode, qrMatrix, qrPath } from './QrCode';

afterEach(cleanup);

const OTPAUTH =
  'otpauth://totp/Nubit%20ERP:admin?secret=6YHXY6YOENLG5XLQU4Q3EW25U2U34IA2&issuer=Nubit%20ERP&algorithm=SHA1&digits=6&period=30';

/** Paints the matrix like a screen would (black on white, with quiet zone) and returns RGBA pixels. */
function rasterise(matrix: boolean[][], scale = 6) {
  const side = (matrix.length + QR_QUIET_ZONE * 2) * scale;
  const data = new Uint8ClampedArray(side * side * 4).fill(255);
  matrix.forEach((row, y) =>
    row.forEach((dark, x) => {
      if (!dark) return;
      for (let dy = 0; dy < scale; dy++) {
        for (let dx = 0; dx < scale; dx++) {
          const px = ((y + QR_QUIET_ZONE) * scale + dy) * side + (x + QR_QUIET_ZONE) * scale + dx;
          data.set([0, 0, 0, 255], px * 4);
        }
      }
    }),
  );
  return { data, side };
}

describe('qrMatrix', () => {
  it('encodes the value so that a scanner reads it back unchanged', () => {
    const { data, side } = rasterise(qrMatrix(OTPAUTH));
    expect(jsQR(data, side, side)?.data).toBe(OTPAUTH);
  });

  it('is square, and bigger for longer data', () => {
    const small = qrMatrix('abc');
    const large = qrMatrix(OTPAUTH);
    expect(small.every((row) => row.length === small.length)).toBe(true);
    expect(large.length).toBeGreaterThan(small.length);
  });
});

describe('qrPath', () => {
  it('draws only dark modules, offset by the quiet zone', () => {
    const path = qrPath([
      [true, true, false],
      [false, false, true],
    ]);
    expect(path).toBe(
      `M${QR_QUIET_ZONE} ${QR_QUIET_ZONE}h2v1h-2z` +
        `M${QR_QUIET_ZONE + 2} ${QR_QUIET_ZONE + 1}h1v1h-1z`,
    );
  });
});

describe('QrCode', () => {
  it('renders an SVG named for its purpose, black on white, without exposing the value', () => {
    render(<QrCode value={OTPAUTH} label="Código QR para tu app de autenticación" />);
    const svg = screen.getByRole('img', { name: 'Código QR para tu app de autenticación' });
    expect(svg.tagName.toLowerCase()).toBe('svg');
    expect(svg.querySelector('rect')?.getAttribute('fill')).toBe('#ffffff');
    expect(svg.querySelector('path')?.getAttribute('fill')).toBe('#000000');
    expect(svg.outerHTML).not.toContain('6YHXY6YOENLG5XLQU4Q3EW25U2U34IA2');
  });
});
