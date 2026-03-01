import { useEffect, useState } from 'react';
import { Setting, settings } from '@assets/Settings';

type Currency = 'PEN' | 'USD';

export function useCurrencySettings() {
  const [currency, setCurrency] = useState<Currency>('PEN');
  const [exchangeRate, setExchangeRate] = useState<string>('3.800000');

  useEffect(() => {
    const currencySetting = settings.getCached(Setting.DEFAULT_CURRENCY);
    const rateSetting = settings.getCached(Setting.DEFAULT_USD_EXCHANGE_RATE);

    const resolved = (currencySetting?.value === 'USD' ? 'USD' : 'PEN') as Currency;
    setCurrency(resolved);

    if (rateSetting?.value && Number(rateSetting.value) > 0) {
      setExchangeRate(rateSetting.value);
    }
  }, []);

  return { currency, exchangeRate, setCurrency, setExchangeRate };
}
