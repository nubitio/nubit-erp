import React, {useEffect} from 'react';

import {HashRouter as Router, useLocation} from 'react-router-dom';

import LoadPanel from 'devextreme-react/load-panel';

import {NavigationProvider} from './contexts/navigation';
import {AuthProvider, useAuth} from './contexts/auth';
import {useScreenSizeClass} from './utils/media-query';
import {Content} from './Content';

import esMessages from 'devextreme/localization/messages/es.json';
import {ThemeContext, useThemeContext} from './theme/theme';
import {loadMessages, locale} from "devextreme/localization";

import './styles/styles.scss';
import './theme/theme';
import config from "devextreme/core/config";

function RootApp() {
    const {user, loading} = useAuth();
    const isLoginForm = [
        'login',
        'reset-password',
        'create-account',
    ].includes(useLocation().pathname.substring(1));

    if (loading) {
        return <LoadPanel visible/>;
    }


    if (user && !isLoginForm) {
        return <Content/>;
    }

    return <div>
        <h1>No estas autenticado</h1>
    </div>
}

export const App = () => {
    const screenSizeClass = useScreenSizeClass();
    const themeContext = useThemeContext();

    useEffect(() => {
        loadMessages(esMessages);
        locale('es-PE')
        config({
            defaultCurrency: 'PEN'
        })
    }, []);

    return (
        <Router>
            <ThemeContext.Provider value={themeContext}>
                <AuthProvider>
                    <NavigationProvider>
                        <div className={`app ${screenSizeClass}`}>
                            {
                                themeContext.isLoaded ? <RootApp/> : ''
                            }
                        </div>
                    </NavigationProvider>
                </AuthProvider>
            </ThemeContext.Provider>
        </Router>
    );
};
