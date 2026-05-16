import { type EventSubscription } from '../../core/event/EventHook';

type Handler = () => void;
const handlers = new Set<Handler>();

export function requestAuthSessionSync(): void {
  handlers.forEach((h) => {
    try {
      h();
    } catch {
      // noop
    }
  });
}

export function subscribeToAuthSessionSync(handler: Handler): EventSubscription {
  handlers.add(handler);
  return {
    unsubscribe: () => handlers.delete(handler),
    add: (teardown: () => void) => {
      const sub = subscribeToAuthSessionSync(handler);
      const outer = sub.unsubscribe;
      sub.unsubscribe = () => {
        outer();
        teardown();
      };
      return sub;
    },
  };
}
