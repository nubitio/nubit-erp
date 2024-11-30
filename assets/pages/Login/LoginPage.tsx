import {useScreenSizeClass} from "../../utils/media-query";
import {useEffect} from "react";
import {loadMessages, locale} from "devextreme/localization";
import config from "devextreme/core/config";
import esMessages from 'devextreme/localization/messages/es.json';

import '../../styles.scss';
import '../../theme/theme';
import './LoginPage.scss';
import {LoginForm} from "./LoginForm";

export const LoginPage = () => {
    const screenSizeClass = useScreenSizeClass();

    useEffect(() => {
        loadMessages(esMessages);
        locale('es-PE')
        config({
            defaultCurrency: 'PEN'
        })
    }, []);

    return (
        <div className={`app ${screenSizeClass}`} id="login-body">
            <div className="container">
                <div className="main">
                    <div style={{maxWidth: "200px", height: "50px", overflow: "hidden"}}>
                        <img src={window.app_logo} className="rounded" style={{height: "50px"}}
                             alt=""/>
                    </div>
                    <div className="main-content">
                        <div>
                            <img src={'assets/images/illustration.svg'} alt=""/>
                            <h2>Efact</h2>
                            <h4 className="text-muted">Simplifica tu trabajo, optimiza tu negocio</h4>
                            <div className="text-muted">{window.app_version}</div>
                        </div>
                    </div>
                </div>
                <div className="sidebar">
                    <div className="row justify-content-center">
                        <div className="col-12 col-sm-6">
                            <h2>Iniciar sesión</h2>
                            <p>Por favor ingresa tus datos para iniciar sesión</p>
                            <LoginForm/>
                        </div>
                        <div className="col-2 d-none d-sm-block"></div>
                    </div>
                </div>
            </div>
        </div>
    );
}
