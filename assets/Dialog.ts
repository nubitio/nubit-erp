import {Constants} from "./Constants";
import {Animation} from "./Animation";
import {EventType} from "./EventType";
import {bus} from "./EventBus";

export default class Dialog {
    private container: HTMLElement;
    private options: any = {};
    private dialog: JQuery;
    private positiveButton: JQuery;
    private negativeButton: JQuery;
    private isShowing: boolean = false;

    constructor(container, options = {}) {
        this.container = document.getElementById(container);
        this.options = options || {};
        this.dialog = null;
        this.positiveButton = null;
        this.negativeButton = null;
        this.init();
    }

    init() {
        this.setupView();
        let ref = this;
        let randomId = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);

        this.dialog.dialog({
            width: this.options.width || 400,
            maxHeight: this.options.maxHeight || 550,
            modal: this.options.modal || true,
            closable: this.options.closable || true,
            buttons: [{
                id: 'negative-button' + randomId,
                text: Constants.TEXT_CANCEL,
                handler: function () {
                    ref.hide();
                    window.Observer.notify(EventType.CANCEL);
                }
            }, {
                id: 'positive-button' + randomId,
                text: Constants.TEXT_SAVE,
                handler: function () {
                    window.Observer.notify(EventType.SAVE);
                }
            }],
            onOpen: function () {
                ref.dialog.dialog('center');
                ref.dialog.parent().animateCss(Animation.DIALOG_OPEN_ANIMATION);
                ref.positiveButton = $('#positive-button' + randomId);
                ref.negativeButton = $('#negative-button' + randomId);
                ref.positiveButton.addClass('md-btn');
                ref.negativeButton.addClass('md-btn-plain');
                ref.isShowing = true;
            },
            onClose: function () {
                ref.isShowing = false;
            }
        });

        this.setupObservers();
    }

    setupView() {
        let dialog = document.createElement('div');
        let randomId = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        dialog.setAttribute('id', randomId);
        this.container.appendChild(dialog);
        this.dialog = $('#' + dialog.id);
    }

    setupObservers() {
        window.Observer.subscribe(EventType.ADD, (data) => {
            this.show('Nuevo');
        });

        bus.on(
            event => event.name === EventType.ADD,
            (event) => {
                this.show('Nuevo');
            }
        );

        window.Observer.subscribe(EventType.EDIT, (data) => {
            this.show('Editar');
        });

        window.Observer.subscribe(EventType.LOADING, (isLoading) => {
            this.setLoading(isLoading);
        });

        window.Observer.subscribe(EventType.SUCCESS, (data) => {
            this.hide();
        });
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