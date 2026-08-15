import { createEndpoints } from '@shared/api/endpoints';

const saleQuotes = createEndpoints('sale-quotes');

export const saleQuoteEndpoints = {
  ...saleQuotes,
  items: (id: string | number) => `${saleQuotes.list()}/${id}/items`,
  convert: (id: string | number) => `${saleQuotes.list()}/${id}/convert`,
  send: (id: string | number) => `${saleQuotes.list()}/${id}/send`,
};
