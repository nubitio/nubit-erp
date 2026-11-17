import { renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { parseFragmentParams, useFragmentParams } from './useFragmentParams';

const NAMES = ['token'] as const;

function setup(entry: string) {
  const wrapper = ({ children }: { children: ReactNode }) => (
    <MemoryRouter initialEntries={[entry]}>{children}</MemoryRouter>
  );
  return renderHook(() => ({ params: useFragmentParams(NAMES), location: useLocation() }), {
    wrapper,
  });
}

describe('parseFragmentParams', () => {
  it('reads key=value pairs, with or without the leading #', () => {
    expect(parseFragmentParams('#token=abc&x=1').get('token')).toBe('abc');
    expect(parseFragmentParams('token=abc').get('token')).toBe('abc');
  });

  it('decodes percent-encoding', () => {
    expect(parseFragmentParams('#token=a%2Bb%3D').get('token')).toBe('a+b=');
  });

  it('treats a legacy hash-router location as no parameters', () => {
    expect(parseFragmentParams('#/reset-password?token=abc').get('token')).toBeNull();
    expect(parseFragmentParams('#/').size).toBe(0);
  });

  it('is empty for no fragment', () => {
    expect(parseFragmentParams('').size).toBe(0);
    expect(parseFragmentParams('#').size).toBe(0);
  });
});

describe('useFragmentParams', () => {
  it('reads the token from the fragment and removes it from the URL', async () => {
    const { result } = setup('/reset-password#token=secret-1');

    expect(result.current.params.token).toBe('secret-1');
    await waitFor(() => expect(result.current.location.hash).toBe(''));
    expect(result.current.location.pathname).toBe('/reset-password');
    // still available to the page after the URL was scrubbed
    expect(result.current.params.token).toBe('secret-1');
  });

  it('falls back to the query string and removes it too', async () => {
    const { result } = setup('/accept-invitation?token=secret-2&keep=1');

    expect(result.current.params.token).toBe('secret-2');
    await waitFor(() => expect(result.current.location.search).toBe('?keep=1'));
  });

  it('prefers the fragment over the query when both are present', async () => {
    const { result } = setup('/x?token=from-query#token=from-fragment');

    expect(result.current.params.token).toBe('from-fragment');
    await waitFor(() =>
      expect(result.current.location.search + result.current.location.hash).toBe(''),
    );
  });

  it('keeps unrelated fragment parameters', async () => {
    const { result } = setup('/x#token=t&step=2');

    await waitFor(() => expect(result.current.location.hash).toBe('#step=2'));
    expect(result.current.params.token).toBe('t');
  });

  it('returns null and leaves the URL alone when there is no token', () => {
    const { result } = setup('/reset-password');

    expect(result.current.params.token).toBeNull();
    expect(result.current.location.pathname).toBe('/reset-password');
    expect(result.current.location.search).toBe('');
  });

  it('does not treat a legacy hash-router location as a token carrier', () => {
    const { result } = setup('/#/reset-password?token=nope');

    expect(result.current.params.token).toBeNull();
  });
});
