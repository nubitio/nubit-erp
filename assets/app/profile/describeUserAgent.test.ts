import { describe, expect, it } from 'vitest';
import { describeUserAgent } from './describeUserAgent';

describe('describeUserAgent', () => {
  it.each([
    [
      'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
      'Chrome en macOS',
    ],
    [
      'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 Edg/126.0',
      'Edge en Windows',
    ],
    ['Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0', 'Firefox en Linux'],
    [
      'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
      'Safari en iOS',
    ],
    [
      'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36',
      'Chrome en Android',
    ],
  ])('names %#', (userAgent, expected) => {
    expect(describeUserAgent(userAgent)).toBe(expected);
  });

  it('does not guess when there is nothing to read', () => {
    expect(describeUserAgent(null)).toBe('Dispositivo desconocido');
    expect(describeUserAgent('curl/8.4.0')).toBe('Dispositivo desconocido');
  });
});
