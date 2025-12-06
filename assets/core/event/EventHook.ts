import { filter, Subject, Subscription, tap } from 'rxjs';
import { useEffect, useRef } from 'react';
import { EventObject } from './EventObject';

const eventBus: Subject<EventObject> = new Subject();

export const useEvents = () => {
  const subscriptionsRef = useRef<Subscription[]>([]);

  useEffect(() => {
    return () => {
      subscriptionsRef.current.forEach((sub: Subscription) => sub.unsubscribe());
      subscriptionsRef.current = [];
    };
  }, []);

  const on = <T>(name: string, handler: (payload: T) => void): Subscription => {
    const sub = eventBus
      .asObservable()
      .pipe(
        filter((event: EventObject) => event.name === name),
        tap((event: EventObject) => handler(event.data)),
      )
      .subscribe();
    subscriptionsRef.current.push(sub);
    return sub;
  };

  const emit = <T>(name: string, payload?: T) => {
    console.log({ name, payload });
    const event: EventObject<T> = { name: name, data: payload };
    eventBus.next(event);
  };

  return [on, emit] as const;
};

export const dispatch = <T>(name: string, payload?: T) => {
  const event: EventObject<T> = { name: name, data: payload };
  eventBus.next(event);
};
