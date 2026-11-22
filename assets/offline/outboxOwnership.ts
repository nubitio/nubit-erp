import { getSessionUser } from '@shared/sessionInfo';

/**
 * Whose queued sale is it?
 *
 * The server attributes a sale — seller, cash movement, permission checks — to whoever is
 * signed in when it arrives, not to whoever made it. On a shared till, the next person to
 * sign in would otherwise send the previous cashier's offline sales as their own. So each
 * queued sale remembers its owner and is only sent from that person's session.
 *
 * Sales queued before this existed have no owner and keep being sent by anyone. Sales signed
 * with the owner's credential (offlineCredential.ts) do not need this: the server knows who
 * made them, so any session may deliver them.
 */
export const currentUserId = (): string | null => {
  const id = getSessionUser()?.userId;
  return id === undefined || id === null || id === '' ? null : String(id);
};

type Owned = { ownerUserId?: string; signed?: boolean };

/**
 * True when the item must wait for its owner: it belongs to someone other than the person
 * signed in now, and carries no signature that lets the server attribute it correctly.
 * A signed sale can be delivered by anyone.
 */
export const belongsToAnotherUser = (item: Owned, signedInUserId: string | null): boolean =>
  item.signed !== true &&
  item.ownerUserId !== undefined &&
  signedInUserId !== null &&
  item.ownerUserId !== signedInUserId;
