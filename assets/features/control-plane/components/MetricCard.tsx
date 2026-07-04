interface MetricCardProps {
  label: string;
  value: number | string;
  tone?: 'default' | 'success' | 'warning' | 'danger';
}

export const MetricCard = ({ label, value, tone = 'default' }: MetricCardProps) => (
  <div className={`cp-metric cp-metric--${tone}`}>
    <span className="cp-metric__label">{label}</span>
    <span className="cp-metric__value">{value}</span>
  </div>
);