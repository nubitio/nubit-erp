// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useState } from 'react';
import type { Field } from '../../field/Field';
import { FieldType } from '../../field/FieldType';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import React from 'react';
import { useDependsOn } from './useDependsOn';

function makeField(overrides: Partial<Field> = {}): Field {
  return {
    isIdentity: false,
    type: FieldType.TEXT,
    col: 12,
    name: 'testField',
    label: 'Test',
    width: undefined,
    height: null,
    minWidth: undefined,
    minHeight: undefined,
    align: 'left',
    sortable: true,
    filterable: true,
    hideable: true,
    validators: [],
    mode: 'local',
    url: undefined,
    loadOptions: [],
    filters: [],
    byKeyUrl: null,
    textField: '',
    valueField: '',
    valueType: 'string',
    format: '',
    selectedFilterOperation: undefined,
    filterValue: undefined,
    data: [],
    formatter: undefined,
    itemFormatter: undefined,
    visible: true,
    defaultValue: undefined,
    onChange: undefined,
    onSelect: undefined,
    onClick: undefined,
    onKeyPress: undefined,
    readonly: false,
    disabled: false,
    hidden: false,
    required: false,
    precision: 0,
    accept: null,
    buttons: [],
    hidingPriority: undefined,
    searchEnabled: true,
    searchExpr: null,
    fixed: false,
    fixedPosition: undefined,
    helpText: undefined,
    contentRender: null,
    visibleOnForm: true,
    maxLength: undefined,
    multiple: false,
    editorOptions: undefined,
    sendAsString: false,
    ...overrides,
  };
}

function createWrapper() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  // Spy on invalidateQueries so we can assert call counts
  const spy = vi.spyOn(queryClient, 'invalidateQueries');

  const wrapper = ({ children }: { children: React.ReactNode }) =>
    React.createElement(QueryClientProvider, { client: queryClient }, children);

  return { queryClient, spy, wrapper };
}

describe('useDependsOn', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('does NOT call invalidateQueries on initial mount with populated formData', () => {
    const { spy, wrapper } = createWrapper();

    const fields: Field[] = [
      makeField({ name: 'city', url: '/api/cities', dependsOn: ['country'] }),
    ];
    const formData = { country: 'ES' };

    renderHook(() => useDependsOn(fields, formData), { wrapper });

    // Mount guard must prevent the first run from calling invalidateQueries
    expect(spy).not.toHaveBeenCalled();
  });

  it('does NOT call invalidateQueries when formData is empty {}', () => {
    const { spy, wrapper } = createWrapper();

    const fields: Field[] = [
      makeField({ name: 'city', url: '/api/cities', dependsOn: ['country'] }),
    ];

    renderHook(() => useDependsOn(fields, {}), { wrapper });

    expect(spy).not.toHaveBeenCalled();
  });

  it('does NOT call invalidateQueries when formData is still null', () => {
    const { spy, wrapper } = createWrapper();

    const fields: Field[] = [
      makeField({ name: 'city', url: '/api/cities', dependsOn: ['country'] }),
    ];

    renderHook(() => useDependsOn(fields, null), { wrapper });

    expect(spy).not.toHaveBeenCalled();
  });

  it('DOES call invalidateQueries when a dependency value changes after mount', () => {
    const { spy, wrapper } = createWrapper();

    const fields: Field[] = [
      makeField({ name: 'city', url: '/api/cities', dependsOn: ['country'] }),
    ];

    const { result } = renderHook(
      () => {
        const [formData, setFormData] = useState<Record<string, unknown>>({ country: 'ES' });
        useDependsOn(fields, formData);
        return { setFormData };
      },
      { wrapper },
    );

    // On mount — no invalidation (mount guard)
    expect(spy).not.toHaveBeenCalled();

    // Simulate user changing the parent field value
    act(() => {
      result.current.setFormData({ country: 'FR' });
    });

    // Now the dependency changed → should invalidate
    expect(spy).toHaveBeenCalledTimes(1);
    expect(spy).toHaveBeenCalledWith({ queryKey: ['/api/cities'] });
  });

  it('DOES fire when dependency transitions from undefined → real value after mount', () => {
    const { spy, wrapper } = createWrapper();

    const fields: Field[] = [
      makeField({ name: 'city', url: '/api/cities', dependsOn: ['country'] }),
    ];

    const { result } = renderHook(
      () => {
        // Start with country = undefined (not present in formData but formData is non-empty)
        const [formData, setFormData] = useState<Record<string, unknown>>({ otherField: 'x' });
        useDependsOn(fields, formData);
        return { setFormData };
      },
      { wrapper },
    );

    // On mount — no invalidation
    expect(spy).not.toHaveBeenCalled();

    // country goes from undefined → 'ES' — this is a real user change
    act(() => {
      result.current.setFormData({ otherField: 'x', country: 'ES' });
    });

    expect(spy).toHaveBeenCalledTimes(1);
    expect(spy).toHaveBeenCalledWith({ queryKey: ['/api/cities'] });
  });

  it('does NOT fire when formData changes but no dependency values changed', () => {
    const { spy, wrapper } = createWrapper();

    const fields: Field[] = [
      makeField({ name: 'city', url: '/api/cities', dependsOn: ['country'] }),
    ];

    const { result } = renderHook(
      () => {
        const [formData, setFormData] = useState<Record<string, unknown>>({
          country: 'ES',
          unrelated: 'A',
        });
        useDependsOn(fields, formData);
        return { setFormData };
      },
      { wrapper },
    );

    expect(spy).not.toHaveBeenCalled();

    // Only a non-dependency field changes
    act(() => {
      result.current.setFormData({ country: 'ES', unrelated: 'B' });
    });

    // country didn't change → no invalidation
    expect(spy).not.toHaveBeenCalled();
  });

  it('skips fields without a url even when dependsOn fires', () => {
    const { spy, wrapper } = createWrapper();

    const fields: Field[] = [
      // Has dependsOn but no url
      makeField({ name: 'city', url: undefined, dependsOn: ['country'] }),
    ];

    const { result } = renderHook(
      () => {
        const [formData, setFormData] = useState<Record<string, unknown>>({ country: 'ES' });
        useDependsOn(fields, formData);
        return { setFormData };
      },
      { wrapper },
    );

    act(() => {
      result.current.setFormData({ country: 'FR' });
    });

    // No url → no invalidation call
    expect(spy).not.toHaveBeenCalled();
  });

  it('handles multiple dependent fields and invalidates each url', () => {
    const { spy, wrapper } = createWrapper();

    const fields: Field[] = [
      makeField({ name: 'city', url: '/api/cities', dependsOn: ['country'] }),
      makeField({ name: 'province', url: '/api/provinces', dependsOn: ['country'] }),
      makeField({ name: 'plain', url: '/api/plain' }), // no dependsOn
    ];

    const { result } = renderHook(
      () => {
        const [formData, setFormData] = useState<Record<string, unknown>>({ country: 'ES' });
        useDependsOn(fields, formData);
        return { setFormData };
      },
      { wrapper },
    );

    expect(spy).not.toHaveBeenCalled();

    act(() => {
      result.current.setFormData({ country: 'FR' });
    });

    // Both city and province should be invalidated, but not plain
    expect(spy).toHaveBeenCalledTimes(2);
    expect(spy).toHaveBeenCalledWith({ queryKey: ['/api/cities'] });
    expect(spy).toHaveBeenCalledWith({ queryKey: ['/api/provinces'] });
  });

  it('invalidates only fields whose own dependencies changed', () => {
    const { spy, wrapper } = createWrapper();

    const fields: Field[] = [
      makeField({ name: 'city', url: '/api/cities', dependsOn: ['country'] }),
      makeField({ name: 'district', url: '/api/districts', dependsOn: ['city'] }),
      makeField({
        name: 'neighborhood',
        url: '/api/neighborhoods',
        dependsOn: ['country', 'city'],
      }),
    ];

    const { result } = renderHook(
      () => {
        const [formData, setFormData] = useState<Record<string, unknown>>({
          country: 'PE',
          city: 'LIM',
        });
        useDependsOn(fields, formData);
        return { setFormData };
      },
      { wrapper },
    );

    expect(spy).not.toHaveBeenCalled();

    act(() => {
      result.current.setFormData({ country: 'CL', city: 'LIM' });
    });

    expect(spy).toHaveBeenCalledTimes(2);
    expect(spy).toHaveBeenCalledWith({ queryKey: ['/api/cities'] });
    expect(spy).toHaveBeenCalledWith({ queryKey: ['/api/neighborhoods'] });
  });

  it('does NOT fire on second render if formData reference changes but values are the same', () => {
    const { spy, wrapper } = createWrapper();

    const fields: Field[] = [
      makeField({ name: 'city', url: '/api/cities', dependsOn: ['country'] }),
    ];

    const { result } = renderHook(
      () => {
        const [formData, setFormData] = useState<Record<string, unknown>>({ country: 'ES' });
        useDependsOn(fields, formData);
        return { setFormData };
      },
      { wrapper },
    );

    expect(spy).not.toHaveBeenCalled();

    // New object reference, same values
    act(() => {
      result.current.setFormData({ country: 'ES' });
    });

    // Deep-equal by JSON.stringify — should NOT fire
    expect(spy).not.toHaveBeenCalled();
  });
});
