/** Calendar dates for Peru; these filters share the grid's server query. */
export function saleFilterPresets(now = new Date()) {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Lima',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(now);
  const part = (type: string) => parts.find((item) => item.type === type)!.value;
  const year = part('year');
  const month = part('month');
  const today = `${year}-${month}-${part('day')}`;
  const lastDay = new Date(Date.UTC(Number(year), Number(month), 0)).getUTCDate();
  return [
    { key: 'today', label: 'Hoy', field: 'issuedDate', operator: '=', value: today },
    {
      key: 'month',
      label: 'Este mes',
      field: 'issuedDate',
      operator: 'between',
      value: `${year}-${month}-01|${year}-${month}-${lastDay}`,
    },
    { key: 'due', label: 'Por cobrar', field: 'paymentState', operator: '=', value: 'due' },
    { key: 'sunat', label: 'Pend. SUNAT', field: 'sunatState', operator: '=', value: 'pending' },
  ];
}
