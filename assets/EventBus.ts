import {filter, Subject, Subscription, tap} from "rxjs";
import {Action} from "./Action";

type Predicate<T> = (item: T) => boolean;

class EventBus<T> {
    private observers: any[];
    private events: Subject<T>;

    constructor() {
        this.observers = [];
        this.events = new Subject();
    }

    on(matcher: Predicate<T>, handler: (item: T) => void): Subscription {
        return this.events
            .asObservable()
            .pipe(filter(matcher), tap(handler))
            .subscribe();
    }

    trigger(item: T) {
        this.events.next(item);
    }

    /**
     * @deprecated(Use listen instead)
     */
    subscribe(name, callback) {
        let f = {name: name, callback: callback};
        this.observers.push(f);
    }

    unsubscribe(name) {
        this.observers = this.observers.filter(subscriber => subscriber.name !== name);
    }

    /**
     * @deprecated(Use trigger instead)
     */
    notify(name, data) {
        this.observers.forEach(observer => {
            if (observer.name === name) {
                observer.callback(data)
            }
        });
    }
}

export const bus = new EventBus<Action>();