<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

use App\Efact\Domain\Money;
use App\Organization\Service\CompanyContext;
use App\Sales\Entity\SaleRecord;
use App\Sales\Port\SaleDocumentLinkInterface;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/** Resolves everything a reminder message needs from a sale and the company. */
final readonly class ReminderContextFactory
{
    public function __construct(
        private CompanyContext $company,
        private SaleDocumentLinkInterface $documentLink,
        private ClockInterface $clock,
    ) {}

    public function create(SaleRecord $sale): ReminderContext
    {
        $customer = $sale->getCustomer();
        $contact = trim((string) $customer?->getCollectionContactName());
        $due = $sale->getDueDate();

        return new ReminderContext(
            companyName: $this->company->getName(),
            contactName: $contact !== '' ? $contact : (string) $customer?->getBusinessName(),
            documentNumber: $sale->getInvoiceFullNumber(),
            amount: self::formatAmount($sale->getBalanceDue(), $sale->getCurrency()),
            dueDate: $due,
            daysOverdue: $this->daysOverdue($due),
            documentUrl: $this->documentLink->publicUrl($sale),
        );
    }

    private function daysOverdue(?DateTimeImmutable $due): int
    {
        if ($due === null) {
            return 0;
        }

        $lima = new DateTimeZone('America/Lima');
        $today = DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone($lima)->setTime(0, 0);
        // A due date is a calendar date: read it as written instead of converting its
        // zone, which would move a midnight UTC date back to the previous day in Lima.
        $dueDay = new DateTimeImmutable($due->format('Y-m-d'), $lima);

        return max(0, (int) $dueDay->diff($today)->format('%r%a'));
    }

    private static function formatAmount(string $amount, string $currency): string
    {
        $symbol = match (strtoupper($currency)) {
            'USD' => 'US$',
            'EUR' => '€',
            default => 'S/',
        };

        return sprintf('%s %s', $symbol, Money::toString(Money::of($amount)));
    }
}
