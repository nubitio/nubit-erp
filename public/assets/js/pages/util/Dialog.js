class Dialog {
    constructor(dialog, observer) {
        this.dialog = $('#' + dialog);
        this.observer = observer;
        this.positiveButton = null;
        this.negativeButton = null;
        this.init();
    }

    init() {
        let ref = this;

        this.dialog.dialog({
            buttons: [{
                id: 'negative-button',
                text: TEXT_CANCEL,
                handler: function () {
                    ref.hide();
                }
            }, {
                id: 'positive-button',
                text: TEXT_SAVE,
                handler: function () {
                    mainObserver.notify(ref.observer);
                }
            }],
            onOpen: function () {
                ref.dialog.dialog('center');
                ref.positiveButton = $('#positive-button');
                ref.negativeButton = $('#negative-button');
                ref.positiveButton.addClass('md-btn');
                ref.negativeButton.addClass('md-btn-plain');
            }
        });
    }

    show(title) {
        this.dialog.dialog('open').dialog('setTitle', title);
    }

    hide() {
        this.dialog.dialog('close');
    }

    setLoading(isLoading) {
        if (isLoading) {
            this.positiveButton.linkbutton('disable');
            this.negativeButton.linkbutton('disable');
            this.positiveButton.linkbutton({text: 'Guardando...'});
            this.dialog.dialog('showMask', 'Procesando...');
        } else {
            this.positiveButton.linkbutton('enable');
            this.negativeButton.linkbutton('enable');
            this.positiveButton.linkbutton({text: TEXT_SAVE});
            this.dialog.dialog('hideMask');
        }
    }
}