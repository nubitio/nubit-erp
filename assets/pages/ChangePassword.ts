import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FormType} from "../core/form/FormType";
import {Field} from "../core/form/Field";
import {FieldBuilder} from "../core/form/FieldBuilder";
import {bus} from "../core/event/EventBus";
import {StorageUtils} from "../core/utils/StorageUtils";
import {messenger} from "../core/utils/Messenger";
import {authService} from "../services/auth.service";
import ApiResponse from "../core/http/ApiResponse";

export default class ChangePassword {
    private readonly container: string;
    private fields: Field[] = [];
    private dialog: Dialog;
    private form: Form;


    static readonly EVENTS = {
        ADD: 'config:add',
        EDIT: 'config:edit',
        DELETE: 'config:delete',
        SAVE: 'config:save',
        CANCEL: 'config:cancel',
        SUCCESS: 'config:success',
        LOADING: 'config:loading',
    }

    constructor(container: string) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .type(FormType.PASSWORD)
            .width(100)
            .name('actual_password')
            .label('Contraseña Actual')
            .required(true)
            .build())
        this.fields.push(new FieldBuilder()
            .type(FormType.PASSWORD)
            .width(100)
            .name('new_password')
            .label('Nueva Contraseña')
            .required(true)
            .build())
        this.fields.push(new FieldBuilder()
            .type(FormType.PASSWORD)
            .width(100)
            .name('confirm_password')
            .label('Confirmar Contraseña')
            .required(true)
            .build())
    };

    render() {
        this.renderDialog();
    }


    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: ChangePassword.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            events: ChangePassword.EVENTS,
            preventDefault: true,
        });

        bus.emit(ChangePassword.EVENTS.ADD, {title: 'Cambiar Contraseña'});

        this.setupObservers();

    }

    setupObservers() {
        bus.on(ChangePassword.EVENTS.CANCEL, (): void => {
            location.href = '/';
        });

        bus.on(ChangePassword.EVENTS.SAVE, (): void => {
            let data: any = this.form.getData();
            let dataObject = this.form.getDataObject();
            let session = StorageUtils.getItem('session');
            let userId = session['user_id'];
            let finalData: string = data + '&user_id=' + userId;

            if (dataObject.new_password !== dataObject.confirm_password) {
                messenger.show('Error', 'Las contraseñas no coinciden');
                return;
            }
            authService.changePassword(userId, dataObject.actual_password, dataObject.new_password ).subscribe((response)=>{
                console.log(response.data)
                messenger.show('Información', 'Contraseña actualizada');
            })
        });
    }
}