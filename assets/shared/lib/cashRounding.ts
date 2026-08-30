/** Peru cash payments are rounded down to the next S/ 0.10 in the customer's favour. */
export const roundCashDownToTenth = (amount: number): number =>
  Math.floor((Math.round(amount * 100) + Number.EPSILON) / 10) / 10;

export const cashRoundingAdjustment = (amount: number): number =>
  Math.round((amount - roundCashDownToTenth(amount)) * 100) / 100;
