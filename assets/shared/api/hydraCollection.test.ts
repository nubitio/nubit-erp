import { describe, expect, it } from 'vitest';
import { hydraMembers } from './hydraCollection';

describe('hydraMembers', () => {
  it('reads a bare array, member and hydra:member', () => {
    expect(hydraMembers([1, 2])).toEqual([1, 2]);
    expect(hydraMembers({ member: [3] })).toEqual([3]);
    expect(hydraMembers({ 'hydra:member': [4] })).toEqual([4]);
  });

  it('is empty for an empty or missing payload', () => {
    expect(hydraMembers({})).toEqual([]);
    expect(hydraMembers(null)).toEqual([]);
    expect(hydraMembers(undefined)).toEqual([]);
  });
});
