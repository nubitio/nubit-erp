import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import Form, {
  ButtonItem,
  ButtonOptions,
  FormRef,
  Item,
  RequiredRule,
} from 'devextreme-react/form';
import LoadIndicator from 'devextreme-react/load-indicator';

import './LoginForm.scss';
import { authService } from '@features/auth';
import { branchService } from '@features/branch';
import { BranchPicker } from '@app/shell/components/BranchPicker';
import { resolveLoginRedirect, resolvePostLoginRedirect } from '@shared/lib/authRedirect';
import { requestAuthSessionSync } from '@shared/lib/authSessionSync';
import { StorageUtils } from '@shared/lib/StorageUtils';

interface BranchOption {
  id: string;
  name: string;
}

export const LoginForm = () => {
  const location = useLocation();
  const navigate = useNavigate();
  const formRef = useRef<FormRef | null>(null);
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const formData = useRef({ username: '', password: '' });
  const [showLocalPicker, setShowLocalPicker] = useState(false);
  const [branchOptions, setBranchOptions] = useState<BranchOption[]>([]);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [redirectPath] = useState(() =>
    resolveLoginRedirect(location.search, window.location.hash),
  );

  useEffect(() => {
    const editor = formRef.current?.instance().getEditor('username');
    editor?.focus();
  }, []);

  const onFieldDataChanged = useCallback(() => {
    if (submitError) {
      setSubmitError(null);
    }
  }, [submitError]);

  const applyBranchAndRedirect = useCallback(
    (branch: BranchOption) => {
      authService.selectBranch(branch.id).then(() => {
          branchService.setSelectedBranchId(branch.id);
          branchService.setSelectedBranchName(branch.name);
          const nextPath = resolvePostLoginRedirect(redirectPath);

          requestAuthSessionSync();
          navigate(nextPath, { replace: true });
        }).catch((err: unknown) => {
          const anyErr = err as { data?: { detail?: string; message?: string } };
          const message =
            anyErr?.data?.detail ||
            anyErr?.data?.message ||
            'No se pudo seleccionar el local. Inténtalo nuevamente.';
          setSubmitError(message);
          setLoading(false);
        });
    },
    [navigate, redirectPath],
  );

  const onSubmit = useCallback(
    async (e: React.FormEvent<HTMLFormElement>) => {
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

      authService.login(username.trim(), password).then(async (response) => {
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

          try {
            const branchesResponse = await branchService.getAll();
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
          } catch (err: unknown) {
            const anyErr = err as { data?: { detail?: string; message?: string } };
            const message =
              anyErr?.data?.detail ||
              anyErr?.data?.message ||
              'No se pudo cargar la lista de locales.';
            setSubmitError(message);
            setLoading(false);
          }
        }).catch((err: unknown) => {
          const anyErr = err as { data?: { detail?: string; message?: string } };
          const message =
            anyErr?.data?.detail ||
            anyErr?.data?.message ||
            'No se pudo iniciar sesión. Verifica tus credenciales e inténtalo de nuevo.';
          setSubmitError(message);
          setLoading(false);
        });
    },
    [loading, applyBranchAndRedirect],
  );

  return (
    <form className="login-form" onSubmit={onSubmit} autoComplete="off">
      <Form
        ref={formRef}
        // eslint-disable-next-line react-hooks/refs -- formData ref holds mutable form state intentionally accessed during render for DevExtreme binding
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
                  icon: showPassword ? 'ph ph-eye' : 'ph ph-eye-slash',
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
        redirectPath={redirectPath}
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
