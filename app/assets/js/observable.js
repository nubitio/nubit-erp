class Observable {

    constructor() {
        this.observers = [];
    }

    subscribe(name, callback) {
        let f = {name: name, callback: callback};
        this.observers.push(f);
    }

    unsubscribe(name) {
        this.observers = this.observers.filter(subscriber => subscriber.name !== name);
    }

    notify(name, data) {
        this.observers.forEach(observer => {
            if (observer.name === name) {
                observer.callback(data)
            }
        });
    }
}