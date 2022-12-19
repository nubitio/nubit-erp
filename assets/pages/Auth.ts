import {Field} from "../Field";
import Form from "../Form";
import {FieldBuilder} from "../FieldBuilder";
import {FormType} from "../FormType";
import {authService} from "../services/auth.service";
import Dialog from "../Dialog";
import {messenger} from "../utils/Messenger";

export default class Auth {
    private readonly container: string;
    private fields: Field[] = [];
    private dialog: Dialog;
    private form: Form;
    private localList: JQuery;

    static readonly ROUTES = {
        LOGIN: 'auth/login',
        LOGOUT: 'auth/logout',
    }

    static readonly EVENTS = {
        LOGIN: 'auth:login',
        LOGOUT: 'auth:logout',
        SUCCESS: 'auth:success',
        LOADING: 'auth:loading',
    }

    constructor(container) {
        this.container = container;
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .type(FormType.TEXT)
            .name('username')
            .label('Usuario')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.PASSWORD)
            .name('password')
            .label('Contraseña')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FormType.SUBMIT)
            .name('submit')
            .label('Ingresar')
            .onClick(() => this.login())
            .build());
    }

    render() {
        this.dialog = new Dialog(this.container, {
            title: 'Seleccione un local',
            height: 250,
            events: Auth.EVENTS,
            positiveText: 'Continuar',
            negativeText: 'Cancelar',
        });

        this.dialog.onPositiveClick(() => {
            let row = this.localList.datalist('getSelected');
            if (row) {
                authService.setLocal(row['local_id']).subscribe((response) => {
                    if (response.success) {
                        this.dialog.hide();
                        messenger.show('Información', 'Bienvenido');
                        window.location.href = '/';
                    }
                });
            }
        });

        let container = document.getElementById(this.dialog.id);
        let dataList = document.createElement('div');
        dataList.setAttribute('id', 'local-list');
        container.append(dataList);

        this.localList = $('#local-list');
        this.localList.datalist({
            method: 'get',
            fit: true,
            valueField: 'local_id',
            textField: 'descripcion',
            lines: true
        });

        this.form = new Form('fm', this.fields, {
            events: Auth.EVENTS,
        });
    }

    login() {
        let username = this.form.getField('username').textbox('getValue');
        let password = this.form.getField('password').textbox('getValue');

        authService.login(username, password).subscribe((response) => {
            if (response.success) {
                this.dialog.show();
                this.localList.datalist('load', 'api/local');
            }
        });
    }

    static logout() {
        authService.logout().subscribe((response) => {
            if (response.success) {
                window.location.href = '/login';
            }
        });
    }
}