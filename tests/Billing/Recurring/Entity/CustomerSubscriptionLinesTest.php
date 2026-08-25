<?php

declare(strict_types=1);

namespace App\Tests\Billing\Recurring\Entity;

use App\Billing\Recurring\Entity\CustomerSubscription;
use App\Billing\Recurring\Entity\CustomerSubscriptionLine;
use PHPUnit\Framework\TestCase;

final class CustomerSubscriptionLinesTest extends TestCase
{
    public function testLinesCanBeManagedFromTheSubscriptionAggregate(): void
    {
        $subscription = new CustomerSubscription();
        $line = new CustomerSubscriptionLine();

        $subscription->addLine($line);

        self::assertCount(1, $subscription->getLines());
        self::assertSame($subscription, $line->getSubscription());

        $subscription->removeLine($line);

        self::assertCount(0, $subscription->getLines());
    }
}
