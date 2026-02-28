import React, { useCallback, useEffect, useRef, useState } from 'react';
import Form, { ButtonItem, ButtonOptions, FormRef, Item, RequiredRule } from 'devextreme-react/form';
import LoadIndicator from 'devextreme-react/load-indicator';

import './LoginForm.scss';
import { authService } from '@features/auth';
import { branchService } from '@features/branch';
import { BranchPicker } from '@widgets/utils/branch-picker/BranchPicker';
import { StorageUtils } from '@shared/lib/StorageUtils';

interface BranchOption {
  id: string;
  name: string;
}

export const LoginForm = () => {
  const formRef = useRef<FormRef | null>(null);
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const formData = useRef({ username: '', password: '' });
  const [showLocalPicker, setShowLocalPicker] = useState(false);
  const [branchOptions, setBranchOptions] = useState<BranchOption[]>([]);
  const [submitError, setSubmitError] = useState<string | null>(null);

  useEffect(() => {
    const editor = formRef.current?.instance().getEditor('username');
    editor?.focus();
  }, []);

  const onFieldDataChanged = useCallback(() => {
    if (submitError) {
      setSubmitError(null);
    }
  }, [submitError]);

  const applyBranchAndRedirect = useCallback((branch: BranchOption) => {
    authService.selectBranch(branch.id).subscribe({
      next: () => {
        branchService.setSelectedBranchId(branch.id);
        branchService.setSelectedBranchName(branch.name);
        window.location.href = '/';
      },
      error: (err) => {
        const message =
          err?.response?.data?.detail ||
          err?.response?.data?.message ||
          'No se pudo seleccionar el local. Inténtalo nuevamente.';
        setSubmitError(message);
        setLoading(false);
      },
    });
  }, []);

  const onSubmit = useCallback(async (e) => {
    e.preventDefault();
    if (loading) {
      return;
    }

    const validationResult = formRef.current?.instance().validate();
    if (validationResult && !validationResult.isValid) {
      setSubmitError('Completa los campos requeridos para continuar.');
      return;
    }

    const { username, password } = formData.current;
    setSubmitError(null);
    setBranchOptions([]);
    setLoading(true);

    authService.login(username.trim(), password).subscribe({
      next: (response) => {
        if (response.data.user) {
          StorageUtils.setItem('user', response.data.user);
        }

        const selectedBranch = response.data.branch;
        if (selectedBranch?.id) {
          applyBranchAndRedirect({
            id: selectedBranch.id,
            name: selectedBranch.name ?? 'Local',
          });
          return;
        }

        branchService.getAll().subscribe({
          next: (branchesResponse) => {
            const branches: BranchOption[] = branchesResponse?.data ?? [];

            if (branches.length === 0) {
              setSubmitError('No hay locales disponibles para este usuario.');
              setLoading(false);
              return;
            }

            if (branches.length === 1) {
              applyBranchAndRedirect(branches[0]);
              return;
            }

            setBranchOptions(branches);
            setShowLocalPicker(true);
            setLoading(false);
          },
          error: (err) => {
            const message =
              err?.response?.data?.detail ||
              err?.response?.data?.message ||
              'No se pudo cargar la lista de locales.';
            setSubmitError(message);
            setLoading(false);
          },
        });
      },
      error: (err) => {
        const message =
          err?.response?.data?.detail ||
          err?.response?.data?.message ||
          'No se pudo iniciar sesión. Verifica tus credenciales e inténtalo de nuevo.';
        setSubmitError(message);
        setLoading(false);
      },
    });
  }, [loading, applyBranchAndRedirect]);

  return (
    <form className="login-form" onSubmit={onSubmit} autoComplete="off">
      <Form
        ref={formRef}
        formData={formData.current}
        onFieldDataChanged={onFieldDataChanged}
        disabled={loading}
        labelMode="hidden"
        showColonAfterLabel={false}
        showRequiredMark={false}
      >
        <Item dataField="username" editorType="dxTextBox" editorOptions={usernameEditorOptions}>
          <RequiredRule message="Usuario es requerido" />
        </Item>
        <Item
          dataField="password"
          editorType="dxTextBox"
          editorOptions={{
            stylingMode: 'outlined',
            placeholder: 'Contraseña',
            mode: showPassword ? 'text' : 'password',
            inputAttr: {
              autoComplete: 'current-password',
              'aria-label': 'Contraseña',
            },
            buttons: [
              {
                name: 'password',
                location: 'after',
                options: {
                  icon: showPassword ? 'eyeopen' : 'eyeclose',
                  type: 'default',
                  stylingMode: 'text',
                  onClick: () => setShowPassword(!showPassword),
                  hint: showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña',
                },
              },
            ],
          }}
        >
          <RequiredRule message="Contraseña es requerida" />
        </Item>
        <ButtonItem>
          <ButtonOptions width="100%" type="default" useSubmitBehavior>
            <span className="dx-button-text">
              {loading ? <LoadIndicator width="24px" height="24px" visible /> : 'Ingresar'}
            </span>
          </ButtonOptions>
        </ButtonItem>
      </Form>

      {submitError && (
        <div className="login-error" role="alert" aria-live="assertive">
          {submitError}
        </div>
      )}

      <BranchPicker
        visible={showLocalPicker}
        setVisible={setShowLocalPicker}
        initialBranches={branchOptions}
      />
    </form>
  );
};

const usernameEditorOptions = {
  stylingMode: 'outlined',
  placeholder: 'Usuario',
  mode: 'text',
  inputAttr: {
    autoComplete: 'username',
    'aria-label': 'Usuario',
  },
  value: '',
};
