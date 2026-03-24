import { Subject, Subscription } from 'rxjs';

const authSessionSync$ = new Subject<void>();

export function requestAuthSessionSync(): void {
  authSessionSync$.next();
}

export function subscribeToAuthSessionSync(handler: () => void): Subscription {
  return authSessionSync$.subscribe(handler);
}
