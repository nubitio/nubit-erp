import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { AppDialog, Button, FormField, TextField } from '@nubitio/ui';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import { CP_STRINGS } from '../../i18n/es';
import { buildImpersonationUrl } from './impersonationUrl';

interface ImpersonateModalProps {
  visible: boolean;
  onClose: () => void;
  tenantId: number;
  tenantName: string;
  primaryDomain: string | null;
}

export const ImpersonateModal = ({
  visible,
  onClose,
  tenantId,
  tenantName,
  primaryDomain,
}: ImpersonateModalProps) => {
  const [username, setUsername] = useState('');
  const [error, setError] = useState<string | null>(null);

  const impersonate = useMutation({
    mutationFn: () => controlPlaneClient.impersonateTenantUser(tenantId, username.trim()),
    onSuccess: (result) => {
      // The token rides in the URL fragment, which is never sent to the server, so it
      // cannot appear in an access log (ADR-020, D3).
      if (!primaryDomain) {
        setError(CP_STRINGS.tenantDetail.impersonateNoDomain);
        return;
      }
      const bridgeUrl = buildImpersonationUrl(window.location, primaryDomain, result.token);
      window.open(bridgeUrl, '_blank', 'noopener,noreferrer');
      handleClose();
    },
    onError: (err: Error) => {
      setError(err.message || CP_STRINGS.tenantDetail.impersonateError);
    },
  });

  const handleClose = () => {
    setUsername('');
    setError(null);
    impersonate.reset();
    onClose();
  };

  const handleSubmit = () => {
    setError(null);
    if (!username.trim()) {
      setError(CP_STRINGS.tenantDetail.impersonateUsernameRequired);
      return;
    }
    if (!primaryDomain) {
      setError(CP_STRINGS.tenantDetail.impersonateNoDomain);
      return;
    }
    impersonate.mutate();
  };

  return (
    <AppDialog
      title={CP_STRINGS.tenantDetail.impersonateTitle}
      visible={visible}
      width={420}
      height="auto"
      showCloseButton={!impersonate.isPending}
      closeOnOutsideClick={!impersonate.isPending}
      closeOnEscape={!impersonate.isPending}
      onClose={handleClose}
      footer={
        <>
          <Button variant="secondary" disabled={impersonate.isPending} onClick={handleClose}>
            {CP_STRINGS.cancel}
          </Button>
          <Button variant="primary" loading={impersonate.isPending} onClick={handleSubmit}>
            {CP_STRINGS.tenantDetail.impersonateSubmit}
          </Button>
        </>
      }
    >
      <p>{CP_STRINGS.tenantDetail.impersonateDescription(tenantName)}</p>
      <FormField label={CP_STRINGS.tenantDetail.impersonateUsernameLabel}>
        <TextField
          value={username}
          disabled={impersonate.isPending}
          placeholder={CP_STRINGS.tenantDetail.impersonateUsernamePlaceholder}
          onChange={(event) => setUsername(event.target.value)}
        />
      </FormField>
      {error && <p role="alert">{error}</p>}
    </AppDialog>
  );
};
