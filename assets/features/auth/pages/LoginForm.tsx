import React, { useCallback, useRef, useState } from 'react';
import Form, { ButtonItem, ButtonOptions, Item, Label, RequiredRule } from 'devextreme-react/form';
import LoadIndicator from 'devextreme-react/load-indicator';

import './LoginForm.scss';
import { authService } from '@features/auth';
import { BranchPicker } from '@widgets/utils/branch-picker/BranchPicker';
import { StorageUtils } from '@shared/lib/StorageUtils';

export const LoginForm = () => {
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const formData = useRef({ username: '', password: '' });
  const [showLocalPicker, setShowLocalPicker] = useState(false);
  const [credentials, setCredentials] = useState({ username: '', password: '' });

  const onSubmit = useCallback(async (e) => {
    e.preventDefault();
    const { username, password } = formData.current;
    setCredentials({ username, password });
    setLoading(true);

    authService.login(username, password).subscribe({
      next: (response) => {
        if (response.data.user) {
          StorageUtils.setItem('user', response.data.user);
        }
        setShowLocalPicker(true);
        setLoading(false);
      },
      error: () => {
        setLoading(false);
      },
    });
  }, []);

  return (
    <form className="login-form" onSubmit={onSubmit}>
      <Form
        formData={formData.current}
        disabled={loading}
        showColonAfterLabel
        showRequiredMark={false}
      >
        <Item dataField="username" editorType="dxTextBox" editorOptions={emailEditorOptions}>
          <RequiredRule message="Usuario es requerido" />
          <Label visible={false} />
        </Item>
        <Item
          dataField="password"
          editorType="dxTextBox"
          editorOptions={{
            stylingMode: 'outlined',
            placeholder: 'Contraseña',
            mode: showPassword ? 'text' : 'password',
            buttons: [
              {
                name: 'password',
                location: 'after',
                options: {
                  icon: showPassword ? 'eyeopen' : 'eyeclose',
                  type: 'default',
                  stylingMode: 'text',
                  onClick: () => setShowPassword(!showPassword),
                },
              },
            ],
          }}
        >
          <RequiredRule message="Contraseña es requerida" />
          <Label visible={false} />
        </Item>
        <ButtonItem>
          <ButtonOptions width="100%" type="default" useSubmitBehavior>
            <span className="dx-button-text">
              {loading ? <LoadIndicator width="24px" height="24px" visible /> : 'Ingresar'}
            </span>
          </ButtonOptions>
        </ButtonItem>
      </Form>
      <BranchPicker
        visible={showLocalPicker}
        setVisible={setShowLocalPicker}
        username={credentials.username}
        password={credentials.password}
      />
    </form>
  );
};

const emailEditorOptions = {
  stylingMode: 'outlined',
  placeholder: 'Usuario',
  mode: 'text',
  value: '',
};
