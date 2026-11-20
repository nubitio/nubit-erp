import { renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { useIdempotencyKey } from './idempotency';

describe('useIdempotencyKey', () => {
  it('reuses the key while the request is unchanged (a retry)', () => {
    const { result } = renderHook(() => useIdempotencyKey('9|10.00|1'));
    expect(result.current.current()).toBe(result.current.current());
  });

  it('changes the key when the request changes', () => {
    const { result, rerender } = renderHook(({ sig }) => useIdempotencyKey(sig), {
      initialProps: { sig: '9|10.00|1' },
    });
    const first = result.current.current();
    rerender({ sig: '9|20.00|1' });
    expect(result.current.current()).not.toBe(first);
  });

  it('starts fresh after renew()', () => {
    const { result } = renderHook(() => useIdempotencyKey('9|10.00|1'));
    const first = result.current.current();
    result.current.renew();
    expect(result.current.current()).not.toBe(first);
  });
});
