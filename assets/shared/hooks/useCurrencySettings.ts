import { useEffect, useState } from 'react';
import { Setting, settings } from '@shared/settings';

type Currency = 'PEN' | 'USD';

export function useCurrencySettings() {
  const [currency, setCurrency] = useState<Currency>('PEN');
  const [exchangeRate, setExchangeRate] = useState<string>('3.800000');

  useEffect(() => {
    const currencySetting = settings.getCached(Setting.DEFAULT_CURRENCY);
    const rateSetting = settings.getCached(Setting.DEFAULT_USD_EXCHANGE_RATE);

    const resolved = (currencySetting?.value === 'USD' ? 'USD' : 'PEN') as Currency;
    // eslint-disable-next-line react-hooks/set-state-in-effect -- initialising derived state from cached settings on mount; no reactive trigger available
    setCurrency(resolved);

    const rateValue = rateSetting?.value;
    if ((typeof rateValue === 'string' || typeof rateValue === 'number') && Number(rateValue) > 0) {
      setExchangeRate(String(rateValue));
    }
  }, []);

  return { currency, exchangeRate, setCurrency, setExchangeRate };
}
