import {Constants} from "./Constants";
import {Animation} from "./Animation";
import {bus} from "./EventBus";
import {Action} from "./Action";
import {SharedUtils} from "./utils/SharedUtils";

export default class Dialog {
    private container: HTMLElement;
    private options: any = {};
    private dialog: JQuery;
    private positiveButton: JQuery;
    private negativeButton: JQuery;
    private isShowing: boolean = false;
    private randomId: string = SharedUtils.randomId();
    private events: any = {};

    constructor(container, options: any = {}) {
        this.container = document.getElementById(container);
        this.options = options || {};
        this.dialog = null;
        this.positiveButton = null;
        this.negativeButton = null;
        this.events = options.events;
        this.init();
    }

    init() {
        this.setupView();
        let ref = this;

        this.dialog.dialog({
            width: this.options.width || 400,
            height: this.options.height || 'auto',
            maxHeight: this.options.maxHeight || 550,
            modal: this.options.modal || true,
            closable: this.options.closable || true,
            buttons: [{
                id: 'negative-button' + this.randomId,
                text: Constants.TEXT_CANCEL,
                handler: function () {
                    ref.hide();
                }
            }, {
                id: 'positive-button' + this.randomId,
                text: Constants.TEXT_SAVE,
                handler: function () {
                    bus.emit(new Action(ref.events.SAVE));
                }
            }],
            onOpen: function () {
                ref.dialog.dialog('center');
                ref.dialog.parent().animateCss(Animation.DIALOG_OPEN_ANIMATION);
                ref.positiveButton = $('#positive-button' + ref.randomId);
                ref.negativeButton = $('#negative-button' + ref.randomId);
                ref.positiveButton.addClass('md-btn');
                ref.negativeButton.addClass('md-btn-plain');
                ref.isShowing = true;
            },
            onClose: function () {
                ref.isShowing = false;
                bus.emit(new Action(ref.events.CANCEL));
            }
        });

        this.setupObservers();
    }

    setupView() {
        let dialog = document.createElement('div');
        dialog.setAttribute('id', this.randomId);
        this.container.appendChild(dialog);
        this.dialog = $('#' + dialog.id);
    }

    setupObservers() {
        bus.on(
            event => event.name === this.events.ADD,
            (event) => {
                this.show('Nuevo');
            }
        );

        bus.on(
            event => event.name === this.events.EDIT,
            (event) => {
                this.show('Editar');
            }
        );

        bus.on(
            event => event.name === this.events.LOADING,
            (event) => {
                this.setLoading(event.data);
            }
        );

        bus.on(
            event => event.name === this.events.SUCCESS,
            (event) => {
                this.hide();
            }
        );
    }

    get id() {
        return this.dialog.attr('id');
    }

    show(title) {
        if (this.isShowing) {
            return;
        }
        this.dialog.dialog('open').dialog('setTitle', title);
    }

    hide() {
        if (!this.isShowing) {
            return;
        }
        this.dialog.dialog('close');
    }

    setLoading(isLoading, title = 'Procesando...') {
        if (isLoading) {
            this.positiveButton.linkbutton('disable');
            this.negativeButton.linkbutton('disable');
            this.positiveButton.linkbutton({text: 'Guardando...'});
            this.dialog.dialog('showMask', title);
        } else {
            this.positiveButton.linkbutton('enable');
            this.negativeButton.linkbutton('enable');
            this.positiveButton.linkbutton({text: Constants.TEXT_SAVE});
            this.dialog.dialog('hideMask');
        }
    }
}