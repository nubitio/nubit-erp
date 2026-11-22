import { getSessionUser } from '@shared/sessionInfo';

/**
 * Whose queued sale is it?
 *
 * The server attributes a sale — seller, cash movement, permission checks — to whoever is
 * signed in when it arrives, not to whoever made it. On a shared till, the next person to
 * sign in would otherwise send the previous cashier's offline sales as their own. So each
 * queued sale remembers its owner and is only sent from that person's session.
 *
 * Sales queued before this existed have no owner and keep being sent by anyone.
 */
export const currentUserId = (): string | null => {
  const id = getSessionUser()?.userId;
  return id === undefined || id === null || id === '' ? null : String(id);
};

type Owned = { ownerUserId?: string };

/** True when the item belongs to someone other than the person signed in now. */
export const belongsToAnotherUser = (item: Owned, signedInUserId: string | null): boolean =>
  item.ownerUserId !== undefined && signedInUserId !== null && item.ownerUserId !== signedInUserId;
