import {filter, Subject, Subscription, tap} from "rxjs";
import {EventObject} from "./EventObject";

class EventBus {
    private events: Subject<EventObject>;

    constructor() {
        this.events = new Subject();
    }

    on<T>(eventName: string, handler: (payload: T) => void): Subscription {
        return this.events
            .asObservable()
            .pipe(
                filter((event: EventObject) => event.name === eventName),
                tap((event: EventObject) => handler(event.data))
            ).subscribe()
    }

    emit<T>(eventName: string, payload?: T): void {
        console.log({eventName, payload});
        const event: EventObject<T> = {name: eventName, data: payload};
        this.events.next(event);
    }
}

export const bus = new EventBus();
