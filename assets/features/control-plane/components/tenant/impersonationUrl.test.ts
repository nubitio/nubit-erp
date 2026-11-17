import { describe, expect, it } from 'vitest';
import { buildImpersonationUrl } from './impersonationUrl';

describe('buildImpersonationUrl', () => {
  it('puts the token in the fragment of a real path, never in the query', () => {
    const url = buildImpersonationUrl({ protocol: 'https:', port: '' }, 'acme.nubit.pe', 'abc123');

    expect(url).toBe('https://acme.nubit.pe/impersonate#token=abc123');
    expect(new URL(url).search).toBe('');
    expect(new URL(url).pathname).toBe('/impersonate');
  });

  it('keeps a non-default port (local development)', () => {
    expect(buildImpersonationUrl({ protocol: 'http:', port: '8080' }, 'demo.localhost', 't')).toBe(
      'http://demo.localhost:8080/impersonate#token=t',
    );
  });

  it('percent-encodes characters that would break the fragment', () => {
    const url = buildImpersonationUrl({ protocol: 'https:', port: '' }, 'a.test', 'x y&z=1#2');
    const fragment = new URL(url).hash;

    expect(fragment).toBe('#token=x%20y%26z%3D1%232');
    expect(new URLSearchParams(fragment.slice(1)).get('token')).toBe('x y&z=1#2');
  });

  it('never produces a hash-router URL', () => {
    expect(buildImpersonationUrl({ protocol: 'https:', port: '' }, 'a.test', 't')).not.toContain(
      '/#/',
    );
  });
});
