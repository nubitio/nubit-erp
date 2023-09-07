import React, {useCallback, useRef, useState} from 'react';

import {useNavigate} from 'react-router-dom';
import Form, {ButtonItem, ButtonOptions, Item, Label, RequiredRule} from 'devextreme-react/form';
import LoadIndicator from 'devextreme-react/load-indicator';

import './LoginForm.scss';
import {authService} from "../../services/auth.service";
import {StorageUtils} from "../../core/utils/StorageUtils";
import {LocalPicker} from "../../components/library/local-picker/LocalPicker";

export const LoginForm = () => {
    const navigate = useNavigate();
    const [loading, setLoading] = useState(false);
    const formData = useRef({username: '', password: ''});
    const [showLocalPicker, setShowLocalPicker] = useState(false);

    const onSubmit = useCallback(async (e) => {
        e.preventDefault();
        const {username, password} = formData.current;
        setLoading(true);

        authService.login(username, password).subscribe({
            next: (response) => {
                StorageUtils.setItem('token', response.data.data.token);
                setShowLocalPicker(true);
            },
            complete: () => {
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
                <Item dataField='password' editorType='dxTextBox' editorOptions={passwordEditorOptions}>
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
            <LocalPicker visible={showLocalPicker} setVisible={setShowLocalPicker}/>
        </form>
    );
};

const emailEditorOptions = {stylingMode: 'outlined', placeholder: 'Usuario', mode: 'text', value: ''};
const passwordEditorOptions = {stylingMode: 'outlined', placeholder: 'Contraseña', mode: 'password', value: ''};
