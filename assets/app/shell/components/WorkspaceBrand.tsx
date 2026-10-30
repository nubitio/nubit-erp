import { useTheme } from '@nubitio/ui';

export function WorkspaceBrand() {
  const { theme } = useTheme();
  return (
    <img
      className="erp-workspace-brand"
      src={
        theme === 'dark'
          ? '/assets/images/nubit-erp-logo-dark.svg'
          : '/assets/images/nubit-erp-logo.svg'
      }
      alt="Nubit ERP"
    />
  );
}
