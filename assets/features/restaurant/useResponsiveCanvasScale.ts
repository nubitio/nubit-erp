import { useEffect, useRef, useState } from 'react';

/**
 * Scales a fixed-size canvas down to fit its container's width — a tablet in
 * a waiter's hand is usually narrower than the 1000px logical floor plan.
 * Never scales up past 1:1 on a wide desktop screen. Coordinates stay in the
 * same logical space either way; the caller divides screen deltas by `scale`
 * to get back logical px (see `toLogical` below).
 */
export function useResponsiveCanvasScale(logicalWidth: number) {
  const containerRef = useRef<HTMLDivElement>(null);
  const [scale, setScale] = useState(1);

  useEffect(() => {
    const element = containerRef.current;
    if (!element) return;

    const update = () => {
      const available = element.clientWidth;
      setScale(available > 0 ? Math.min(1, available / logicalWidth) : 1);
    };

    update();
    const observer = new ResizeObserver(update);
    observer.observe(element);
    return () => observer.disconnect();
  }, [logicalWidth]);

  return { containerRef, scale };
}

/** Converts a screen (client) coordinate into the canvas's unscaled logical space. */
export function toLogical(clientCoord: number, rectStart: number, scale: number): number {
  return (clientCoord - rectStart) / scale;
}
