import Dialog from "../core/dialog/Dialog";
import {authService} from "../services/auth.service";
import {bus} from "../core/event/EventBus";
import {Constants} from "../core/common/Constants";

export default class LocalPicker {
    private readonly container: string;
    private dialog: Dialog;
    private localList: JQuery;

    static readonly EVENTS = {
        PICKED: 'local-picker:picked',
        CANCELED: 'local-picker:canceled',
    }

    constructor(container: string) {
        this.container = container;
        this.init();
    }

    init() {
        this.dialog = new Dialog(this.container, {
            title: 'Seleccione un local',
            height: 250,
            inline: false,
            positiveText: 'Continuar',
            negativeText: 'Cancelar',
        });

        this.dialog.onPositiveClick(() => {
            let row = this.localList.datalist('getSelected');
            if (row) {
                authService.setLocal(row['local_id']).subscribe((response) => {
                    if (response.data.success) {
                        this.dialog.hide();
                        bus.emit(LocalPicker.EVENTS.PICKED, response.data.data);
                    }
                });
            }
        });

        this.dialog.onNegativeClick(() => {
            this.dialog.hide();
            bus.emit(LocalPicker.EVENTS.CANCELED);
        });

        let container = document.getElementById(this.dialog.id ?? '');

        if (!container) {
            return;
        }

        let dataList = document.createElement('div');
        dataList.setAttribute('id', 'local-list');
        container.append(dataList);

        this.localList = $('#local-list');
        this.localList.datalist({
            method: 'get',
            url: Constants.API_BASE_URL + 'branches',
            fit: true,
            valueField: 'local_id',
            textField: 'descripcion',
            lines: true
        });
    }

    show() {
        this.dialog.show();
    }
}
