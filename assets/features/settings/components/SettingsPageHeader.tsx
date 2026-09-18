interface SettingsPageHeaderProps {
  title: string;
  description: string;
}

export const SettingsPageHeader = ({ title, description }: SettingsPageHeaderProps) => (
  <header className="settings-page-header">
    <h2>{title}</h2>
    <p>{description}</p>
  </header>
);
