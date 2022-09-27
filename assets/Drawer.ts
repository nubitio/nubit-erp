import {EventType} from "./EventType";
import {bus} from "./EventBus";

class Drawer {
    private options: any = {};
    private drawer: JQuery;

    constructor(options = {}) {
        this.options = options || {};
        this.drawer = null;
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
            action => action.name === EventType.SUCCESS,
            (action) => {
                this.hide();
            }
        )

        bus.on(
            action => action.name === EventType.CANCEL,
            (action) => {
                this.hide();
            }
        )

        bus.on(
            action => action.name === EventType.DESTROY,
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

export const drawer = new Drawer();