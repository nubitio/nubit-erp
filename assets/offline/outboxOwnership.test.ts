import { describe, expect, it } from 'vitest';
import { belongsToAnotherUser } from './outboxOwnership';

describe('belongsToAnotherUser', () => {
  it('lets the owner send their own sale', () => {
    expect(belongsToAnotherUser({ ownerUserId: '7' }, '7')).toBe(false);
  });

  it('holds back a sale made by someone else on the same till', () => {
    expect(belongsToAnotherUser({ ownerUserId: '7' }, '9')).toBe(true);
  });

  it('keeps sending sales queued before owners were recorded', () => {
    expect(belongsToAnotherUser({}, '9')).toBe(false);
  });

  it('does not hold anything back when the signed-in user cannot be determined', () => {
    expect(belongsToAnotherUser({ ownerUserId: '7' }, null)).toBe(false);
  });
});
