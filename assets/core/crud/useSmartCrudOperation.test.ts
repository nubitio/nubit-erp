// @vitest-environment jsdom

import { describe, it, expect, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { dispatch } from '../event/EventHook';
import { createScopedEventBus } from '../event/createScopedEventBus';
import { useSmartCrudOperation } from './useSmartCrudOperation';

function makeEvents(id = 'test') {
  return createScopedEventBus(id);
}

describe('useSmartCrudOperation', () => {
  let events: ReturnType<typeof makeEvents>;

  beforeEach(() => {
    events = makeEvents();
  });

  it('initializes with null operation and null formData when no routing', () => {
    const { result } = renderHook(() =>
      useSmartCrudOperation(events, { initialRecordId: null, initialIsNew: false }),
    );

    expect(result.current.activeOperation).toBe(null);
    expect(result.current.formData).toBe(null);
  });

  it('initializes with edit when routing has a record id', () => {
    const { result } = renderHook(() =>
      useSmartCrudOperation(events, { initialRecordId: '42', initialIsNew: false }),
    );

    expect(result.current.activeOperation).toBe('edit');
  });

  it('initializes with create when routing has initialIsNew', () => {
    const { result } = renderHook(() =>
      useSmartCrudOperation(events, { initialRecordId: null, initialIsNew: true }),
    );

    expect(result.current.activeOperation).toBe('create');
  });

  it('sets create operation on ADD event', () => {
    const { result } = renderHook(() =>
      useSmartCrudOperation(events, { initialRecordId: null, initialIsNew: false }),
    );

    act(() => {
      dispatch(events.ADD!);
    });

    expect(result.current.activeOperation).toBe('create');
    expect(result.current.formData).toEqual({});
  });

  it('sets edit operation on EDIT event', () => {
    const { result } = renderHook(() =>
      useSmartCrudOperation(events, { initialRecordId: null, initialIsNew: false }),
    );

    act(() => {
      dispatch(events.EDIT!);
    });

    expect(result.current.activeOperation).toBe('edit');
    expect(result.current.formData).toBe(null);
  });

  it('resets on CANCEL event', () => {
    const { result } = renderHook(() =>
      useSmartCrudOperation(events, { initialRecordId: null, initialIsNew: false }),
    );

    act(() => {
      dispatch(events.ADD!);
    });
    expect(result.current.activeOperation).toBe('create');

    act(() => {
      dispatch(events.CANCEL!);
    });

    expect(result.current.activeOperation).toBe(null);
    expect(result.current.formData).toBe(null);
  });

  it('resets on SUCCESS event', () => {
    const { result } = renderHook(() =>
      useSmartCrudOperation(events, { initialRecordId: null, initialIsNew: false }),
    );

    act(() => {
      dispatch(events.ADD!);
    });
    expect(result.current.activeOperation).toBe('create');

    act(() => {
      dispatch(events.SUCCESS!);
    });

    expect(result.current.activeOperation).toBe(null);
    expect(result.current.formData).toBe(null);
  });

  it('updates formData via handleFormDataChange', () => {
    const { result } = renderHook(() =>
      useSmartCrudOperation(events, { initialRecordId: null, initialIsNew: false }),
    );

    act(() => {
      result.current.handleFormDataChange({ name: 'test' });
    });

    expect(result.current.formData).toEqual({ name: 'test' });
  });

  it('handleFormDataChange is referentially stable', () => {
    const { result, rerender } = renderHook(() =>
      useSmartCrudOperation(events, { initialRecordId: null, initialIsNew: false }),
    );

    const first = result.current.handleFormDataChange;
    rerender();
    expect(result.current.handleFormDataChange).toBe(first);
  });

  it('syncs from routing changes', () => {
    let routingState = { initialRecordId: null as string | null, initialIsNew: false };

    const { result, rerender } = renderHook(() =>
      useSmartCrudOperation(events, routingState),
    );

    expect(result.current.activeOperation).toBe(null);

    routingState = { initialRecordId: '10', initialIsNew: false };
    rerender();

    expect(result.current.activeOperation).toBe('edit');
    expect(result.current.formData).toBe(null);

    routingState = { initialRecordId: null, initialIsNew: true };
    rerender();

    expect(result.current.activeOperation).toBe('create');
    expect(result.current.formData).toEqual({});
  });
});
