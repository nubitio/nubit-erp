import {bus} from "../event/EventBus";

export class Drawer {
    private options: any = {};
    private drawer: JQuery;
    private events: any = null;

    constructor(options: any = {}) {
        this.options = options || {};
        this.events = options.events ?? null;
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
        let drawerContainer = document.createElement('div');
        let randomId = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        drawerContainer.setAttribute('id', randomId);
        document.body.appendChild(drawerContainer);
        this.drawer = $('#' + randomId);
    }

    setupObservers() {
        bus.on(this.events?.SUCCESS, () => this.hide());

        bus.on(this.events?.CANCEL, () => this.hide());

        bus.on(this.events?.DESTROY, () => this.drawer.drawer('destroy'))
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