<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Sales\Domain\CustomerAccountBackfillMode;
use Doctrine\DBAL\Exception;

/**
 * One-off backfill of `customer_account_entries` (#367 / #384) from the history
 * that predates the ledger: every non-voided credit sale becomes a CHARGE and
 * every `sale_payments` row against one a PAYMENT, walked chronologically per
 * customer (see {@see CustomerAccountTimeline}) so `running_balance` is correct.
 *
 * Scope rule: a customer is backfilled only when they have **zero** ledger
 * entries. Customers with some already (new activity from the SaleConfirmed
 * handler or the collection flow) are reported and skipped — mixing a partial
 * live ledger with a historical rebuild would need a full per-customer
 * recompute, out of scope for a rollout tool.
 */
final readonly class CustomerAccountBackfiller
{
    public function __construct(
        private CustomerAccountBackfillGateway $gateway,
    ) {}

    /**
     * @return array{customers: int, charges: int, payments: int, skipped: list<int>}
     *
     * @throws Exception
     */
    public function run(CustomerAccountBackfillMode $mode): array
    {
        $alreadyLedgered = $this->gateway->customersWithEntries();

        $chargesByCustomer = [];
        foreach ($this->gateway->chargeRows() as $row) {
            $chargesByCustomer[(int) $row['customer_id']][] = $row;
        }
        $paymentsByCustomer = [];
        foreach ($this->gateway->paymentRows() as $row) {
            $paymentsByCustomer[(int) $row['customer_id']][] = $row;
        }

        $customers = 0;
        $charges = 0;
        $payments = 0;
        $skipped = [];

        foreach (array_keys($chargesByCustomer + $paymentsByCustomer) as $customerId) {
            if (in_array($customerId, $alreadyLedgered, strict: true)) {
                $skipped[] = $customerId;

                continue;
            }

            $timeline = CustomerAccountTimeline::merge(
                $chargesByCustomer[$customerId] ?? [],
                $paymentsByCustomer[$customerId] ?? [],
            );
            $customers++;

            foreach ($timeline as $entry) {
                $entry['entry_type'] === 'charge' ? $charges++ : $payments++;
                if ($mode->writes()) {
                    $this->gateway->insertEntry($customerId, $entry);
                }
            }
        }

        return ['customers' => $customers, 'charges' => $charges, 'payments' => $payments, 'skipped' => $skipped];
    }
}
