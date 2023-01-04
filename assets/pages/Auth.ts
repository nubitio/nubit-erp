import {Field} from "../core/form/Field";
import Form from "../core/form/Form";
import {FieldBuilder} from "../core/form/FieldBuilder";
import {FormType} from "../core/form/FormType";
import {authService} from "../services/auth.service";
import {messenger} from "../core/utils/Messenger";
import LocalPicker from "../modules/LocalPicker";
import {bus} from "../core/event/EventBus";
import {StorageUtils} from "../core/utils/StorageUtils";

export default class Auth {
    private readonly container: string;
    private fields: Field[] = [];
    private form: Form;
    private localPicker: LocalPicker;

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

    constructor(container: string) {
        this.container = container;
        this.localPicker = new LocalPicker(this.container);
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

        this.setupObservers();
    }

    setupObservers() {
        bus.on(
            event => event.name === LocalPicker.EVENTS.PICKED,
            (event) => {
                StorageUtils.setItem('session', event.data);
                messenger.show('Información', 'Bienvenido');
                window.location.href = '/';
            }
        )

        bus.on(
            event => event.name === LocalPicker.EVENTS.CANCELED,
            () => {
                window.location.href = '/logout';
            }
        )
    }

    render() {
        this.form = new Form('fm', this.fields, {
            events: Auth.EVENTS,
        });
    }

    login() {
        let username = this.form.getField('username').textbox('getValue');
        let password = this.form.getField('password').textbox('getValue');

        let loginButton = this.form.getField('submit');
        loginButton.linkbutton('disable');

        authService.login(username, password).subscribe({
            next: (response) => {
                if (response.success) {
                    this.localPicker.show();
                }
            },
            error: (error) => {
                loginButton.linkbutton('enable');
            }
        });
    }
}