import {Field} from "../Field";
import Form from "../Form";
import {FieldBuilder} from "../FieldBuilder";
import {FormType} from "../FormType";
import {authService} from "../services/auth.service";
import {messenger} from "../utils/Messenger";
import LocalPicker from "../modules/LocalPicker";
import {bus} from "../EventBus";

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

    constructor(container) {
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
            () => {
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

        authService.login(username, password).subscribe((response) => {
            if (response.success) {
                this.localPicker.show();
            }
        });
    }
}