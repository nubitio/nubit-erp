import {Constants} from "../common/Constants";
import {Animation} from "../common/Animation";
import {bus} from "../event/EventBus";
import {SharedUtils} from "../utils/SharedUtils";
import {Button} from "../types";

export default class Dialog {
    private container: HTMLElement;
    private options: any = {};
    private dialog: JQuery;
    private positiveButton: JQuery;
    private negativeButton: JQuery;
    private isShowing: boolean = false;
    private randomId: string = SharedUtils.randomId();
    private events: any = null;

    constructor(container: string, options: any = {}) {
        this.container = document.getElementById(container) ?? document.body;
        this.options = options || {};
        this.events = options.events ?? null;
        this.init();
    }

    init() {
        this.setupView();
        let ref = this;

        let buttons: Button[] = [];
        if (this.options.closable ?? true) {
            buttons.push({
                id: 'negative-button' + this.randomId,
                text: this.options.negativeText ?? Constants.TEXT_CANCEL,
                handler: () => {
                    ref.hide();
                }
            });
        }
        buttons.push({
            id: 'positive-button' + this.randomId,
            text: this.options.positiveText ?? Constants.TEXT_SAVE,
            handler: () => {
                if (this.events?.SAVE) {
                    bus.emit(this.events?.SAVE);
                }
            }
        });

        this.dialog.dialog({
            title: this.options.title ?? 'Nuevo',
            width: this.options.width || 400,
            height: this.options.height || 'auto',
            maxHeight: this.options.maxHeight || 550,
            modal: this.options.modal || true,
            inline: this.options.inline || false,
            closable: this.options.closable ?? false,
            href: this.options.href ?? null,
            buttons: buttons,
            onOpen: function () {
                ref.dialog.dialog('center');
                ref.dialog.parent().animateCss(Animation.DIALOG_OPEN_ANIMATION);
                ref.positiveButton.addClass('md-btn');
                ref.negativeButton.addClass('md-btn-plain');
                ref.isShowing = true;
            },
            onClose: function () {
                ref.isShowing = false;
                if (ref.events?.CANCEL) {
                    bus.emit(ref.events?.CANCEL);
                }
            }
        });

        ref.positiveButton = $('#positive-button' + ref.randomId);
        ref.negativeButton = $('#negative-button' + ref.randomId);

        this.setupObservers();
    }

    setupView() {
        let dialog = document.createElement('div');
        dialog.setAttribute('id', this.randomId);
        this.container.appendChild(dialog);
        this.dialog = $('#' + dialog.id);
    }

    setupObservers() {
        bus.on<any>(this.events?.ADD, (payload) => this.show(payload?.title ?? 'Nuevo'));

        bus.on(this.events?.EDIT, () => this.show('Editar'));

        bus.on<boolean>(this.events?.LOADING, (isLoading) => this.setLoading(isLoading));

        bus.on(this.events?.SUCCESS, () => this.hide());
    }

    get id(): string {
        return this.dialog.attr('id') ?? '';
    }

    show(title = 'Nuevo') {
        if (this.isShowing) {
            return;
        }
        this.dialog.dialog('open').dialog('setTitle', this.options.title ?? title);
    }

    hide() {
        if (!this.isShowing) {
            return;
        }
        this.dialog.dialog('close');
    }

    setLoading(isLoading: boolean, title = 'Procesando...') {
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

    onPositiveClick(callback: () => void) {
        this.positiveButton.on('click', callback);
    }

    onNegativeClick(callback: () => void) {
        this.negativeButton.on('click', callback);
    }
}