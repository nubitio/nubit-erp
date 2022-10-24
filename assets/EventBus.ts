import {filter, Subject, Subscription, tap} from "rxjs";
import {Event} from "./Event";

type Predicate<T> = (item: T) => boolean;

class EventBus<T> {
    private events: Subject<T>;

    constructor() {
        this.events = new Subject();
    }

    on(matcher: Predicate<T>, handler: (item: T) => void): Subscription {
        return this.events
            .asObservable()
            .pipe(filter(matcher), tap(handler))
            .subscribe();
    }

    emit(item: T) {
        console.log(item)
        this.events.next(item);
    }
}

export const bus = new EventBus<Event>();