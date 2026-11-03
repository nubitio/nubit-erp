<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

use App\Sales\Entity\SaleRecord;

/** Picks the customer's contact data for the chosen channel, or fails with a 422. */
final class ReminderRecipientResolver
{
    public function resolve(SaleRecord $sale, ReminderChannel $channel): ReminderRecipient
    {
        $customer = $sale->getCustomer();

        if ($channel === ReminderChannel::Whatsapp) {
            $number = WhatsappNumber::normalize($customer?->getPhone());

            return $number === null
                ? throw ReminderException::missingRecipient($channel)
                : new ReminderRecipient(phone: $number, email: null);
        }

        $email = trim((string) $customer?->getEmail());

        return $email === ''
            ? throw ReminderException::missingRecipient($channel)
            : new ReminderRecipient(phone: null, email: $email);
    }
}
