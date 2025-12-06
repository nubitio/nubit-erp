import React, {useCallback, useRef, useState} from 'react';

import Form, {ButtonItem, ButtonOptions, Item, Label, RequiredRule} from 'devextreme-react/form';
import LoadIndicator from 'devextreme-react/load-indicator';

import './LoginForm.scss';
import {authService} from "../../../service/auth.service";
import {StorageUtils} from "../../../core/utils/StorageUtils";
import {LocalPicker} from "../../utils/local-picker/LocalPicker";

export const LoginForm = () => {
    const [loading, setLoading] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const formData = useRef({username: '', password: ''});
    const [showLocalPicker, setShowLocalPicker] = useState(false);
    const [user, setUser] = useState('');
    const [password, setPassword] = useState('');

    const onSubmit = useCallback(async (e) => {
        e.preventDefault();
        const {username, password} = formData.current;
        setUser(username);
        setPassword(password);
        setLoading(true);

        authService.login(username, password).subscribe({
            next: (response) => {
                StorageUtils.setItem('token', response.data.token);
                setShowLocalPicker(true);
            },
            error: (error) => {
                console.error(error);
                setLoading(false);
            }
        });
    }, []);

    return (
        <form className='login-form' onSubmit={onSubmit}>
            <Form
                formData={formData.current}
                disabled={loading}
                showColonAfterLabel
                showRequiredMark={false}
            >
                <Item dataField='username' editorType='dxTextBox' editorOptions={emailEditorOptions}>
                    <RequiredRule message='Usuario es requerido'/>
                    <Label visible={false}/>
                </Item>
                <Item
                    dataField='password'
                    editorType='dxTextBox'
                    editorOptions={{
                        stylingMode: 'outlined',
                        placeholder: 'Contraseña',
                        mode: showPassword ? 'text' : 'password',
                        buttons: [{
                            name: 'password',
                            location: 'after',
                            options: {
                                icon: showPassword ? 'eyeopen' : 'eyeclose',
                                type: 'default',
                                stylingMode: 'text',
                                onClick: () => setShowPassword(!showPassword)
                            }
                        }]
                    }}
                >
                    <RequiredRule message='Contraseña es requerida'/>
                    <Label visible={false}/>
                </Item>
                <ButtonItem>
                    <ButtonOptions width='100%' type='default' useSubmitBehavior>
                        <span className='dx-button-text'>{loading ?
                            <LoadIndicator width='24px' height='24px' visible/> : 'Ingresar'}</span>
                    </ButtonOptions>
                </ButtonItem>
            </Form>
            <LocalPicker visible={showLocalPicker} setVisible={setShowLocalPicker} username={user} password={password}/>
        </form>
    );
};

const emailEditorOptions = {stylingMode: 'outlined', placeholder: 'Usuario', mode: 'text', value: ''};
