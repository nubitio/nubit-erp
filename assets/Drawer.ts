import {bus} from "./EventBus";

export class Drawer {
    private options: any = {};
    private drawer: JQuery;
    private events: any = {};

    constructor(options: any = {}) {
        this.options = options || {};
        this.drawer = null;
        this.events = options.events || {};
        this.init();
    }

    init() {
        this.setupView();

        this.drawer.drawer({
            width: this.options.width || 350,
            region: this.options.region || 'east',
        });

        this.setupObservers();
    }

    setupView() {
        let drawer = document.createElement('div');
        let randomId = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        drawer.setAttribute('id', randomId);
        document.body.appendChild(drawer);
        this.drawer = $('#' + randomId);
    }

    setupObservers() {
        bus.on(
            action => action.name === this.events.SUCCESS,
            (action) => {
                this.hide();
            }
        )

        bus.on(
            action => action.name === this.events.CANCEL,
            (action) => {
                this.hide();
            }
        )

        bus.on(
            action => action.name === this.events.DESTROY,
            (action) => {
                this.drawer.drawer('destroy');
            }
        )
    }

    get id() {
        return this.drawer.attr('id');
    }

    show() {
        this.drawer.drawer({})
        this.drawer.drawer('expand');
    }

    hide() {
        this.drawer.drawer('collapse');
    }
}