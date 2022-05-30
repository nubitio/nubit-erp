import {Constants} from "./Constants";
import {Animation} from "./Animation";
import {EventType} from "./EventType";

export default class Dialog {
    private container: HTMLElement;
    private dialog: JQuery;
    private positiveButton: JQuery;
    private negativeButton: JQuery;

    constructor(container) {
        this.container = document.getElementById(container);
        this.dialog = null;
        this.positiveButton = null;
        this.negativeButton = null;
        this.init();
    }

    init() {
        this.setupView();
        let ref = this;

        this.dialog.dialog({
            buttons: [{
                id: 'negative-button',
                text: Constants.TEXT_CANCEL,
                handler: function () {
                    ref.hide();
                }
            }, {
                id: 'positive-button',
                text: Constants.TEXT_SAVE,
                handler: function () {
                    window.Observer.notify(EventType.SAVE);
                }
            }],
            onOpen: function () {
                ref.dialog.dialog('center');
                ref.dialog.parent().animateCss(Animation.DIALOG_OPEN_ANIMATION);
                ref.positiveButton = $('#positive-button');
                ref.negativeButton = $('#negative-button');
                ref.positiveButton.addClass('md-btn');
                ref.negativeButton.addClass('md-btn-plain');
            }
        });
    }

    setupView() {
        let dialog = document.createElement('div');
        dialog.setAttribute('id', 'dialog');
        this.container.appendChild(dialog);
        this.dialog = $('#' + dialog.id);
    }

    get id() {
        return this.dialog.attr('id');
    }

    show(title) {
        this.dialog.dialog('open').dialog('setTitle', title);
    }

    hide() {
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