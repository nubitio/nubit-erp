const NUBIT_ICON = '/assets/images/nubit-icon.png';

/** Same mark as the login brand panel: the Nubit "N" icon plus the product name. */
export function WorkspaceBrand() {
  return (
    <span className="erp-workspace-brand">
      <img src={NUBIT_ICON} alt="" />
      <span>Nubit ERP</span>
    </span>
  );
}
