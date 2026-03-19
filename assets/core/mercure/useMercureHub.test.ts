// @vitest-environment jsdom
/**
 * Unit tests for useMercureHub — SLICE-014 T014-7
 *
 * Coverage:
 *   - Without MercureProvider → returns null (AC-4)
 *   - With MercureProvider hubUrl="https://hub.example.com" → returns the URL (AC-4)
 *   - With MercureProvider hubUrl=null → returns null (AC-8)
 */

import { describe, it, expect } from 'vitest';
import { renderHook } from '@testing-library/react';
import React from 'react';
import { useMercureHub } from './useMercureHub';
import { MercureProvider } from './MercureProvider';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useMercureHub', () => {
  it('without MercureProvider → returns null (default context value)', () => {
    const { result } = renderHook(() => useMercureHub());

    expect(result.current).toBeNull();
  });

  it('with MercureProvider hubUrl="https://hub.example.com" → returns the URL', () => {
    const hubUrl = 'https://hub.example.com/.well-known/mercure';

    const wrapper = ({ children }: { children: React.ReactNode }) =>
      React.createElement(MercureProvider, { hubUrl, children });

    const { result } = renderHook(() => useMercureHub(), { wrapper });

    expect(result.current).toBe(hubUrl);
  });

  it('with MercureProvider hubUrl=null → returns null (hub not yet discovered)', () => {
    const wrapper = ({ children }: { children: React.ReactNode }) =>
      React.createElement(MercureProvider, { hubUrl: null, children });

    const { result } = renderHook(() => useMercureHub(), { wrapper });

    expect(result.current).toBeNull();
  });

  it('hub URL updates when MercureProvider prop changes', () => {
    let hubUrl: string | null = null;

    const wrapper = ({ children }: { children: React.ReactNode }) =>
      React.createElement(MercureProvider, { hubUrl, children });

    const { result, rerender } = renderHook(() => useMercureHub(), { wrapper });

    expect(result.current).toBeNull();

    // Simulate hub URL being discovered
    hubUrl = 'https://hub.example.com/.well-known/mercure';
    rerender();

    expect(result.current).toBe('https://hub.example.com/.well-known/mercure');
  });
});
