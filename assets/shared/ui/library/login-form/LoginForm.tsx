import React, { useCallback, useRef, useState } from 'react';

import Form, { ButtonItem, ButtonOptions, Item, Label, RequiredRule } from 'devextreme-react/form';
import LoadIndicator from 'devextreme-react/load-indicator';

import './LoginForm.scss';
import { authService } from '@features/auth';
import { BranchPicker } from '../../utils/branch-picker/BranchPicker';
import toast from '@shared/lib/SharedUtils';

export const LoginForm = () => {
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const formData = useRef({ username: '', password: '' });
  const [showLocalPicker, setShowLocalPicker] = useState(false);
  const onSubmit = useCallback(async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const { username, password } = formData.current;
    setLoading(true);

    authService.login(username, password).subscribe({
      next: (_response) => {
        setShowLocalPicker(true);
      },
      error: () => {
        toast('Usuario o contraseña incorrectos', 'error');
        setLoading(false);
      },
    });
  }, []);

  return (
    <form className="login-form" onSubmit={onSubmit}>
      <Form
        // eslint-disable-next-line react-hooks/refs -- formData ref holds mutable form state intentionally accessed during render for DevExtreme binding
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
                  icon: showPassword ? 'ph ph-eye' : 'ph ph-eye-slash',
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
      <BranchPicker visible={showLocalPicker} setVisible={setShowLocalPicker} />
    </form>
  );
};

const emailEditorOptions = {
  stylingMode: 'outlined',
  placeholder: 'Usuario',
  mode: 'text',
  value: '',
};
