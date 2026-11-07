<?php

declare(strict_types=1);

namespace App\Tests\Payroll\Service;

use App\Accounting\Message\EconomicEvent;
use App\Accounting\Service\DefaultPostingService;
use App\Catalog\Entity\PaymentMethod;
use App\Payroll\Entity\PayrollRun;
use App\Payroll\Entity\PayslipEntry;
use App\Payroll\Service\PayrollAccountingService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class PayrollAccountingServiceTest extends TestCase
{
    public function testAccrualEventBalancesAgainstPostingRules(): void
    {
        $captured = [];
        $service = $this->service([$this->entry(
            '3000.00',
            '0.00',
            '270.00',
            '390.00',
            '0.00',
            '0.00',
            '2610.00',
        )], $captured);

        $service->postAccrual($this->payrollRun());

        self::assertCount(1, $captured);
        $event = $captured[0];
        self::assertSame(EconomicEvent::PAYROLL_ACCRUAL, $event->kind);
        self::assertSame('3270.00', $event->total);

        $lines = $this->postingService()->linesFor($event);
        $debit = $credit = 0.0;
        foreach ($lines as $line) {
            $debit += (float) ($line['debit'] ?? 0);
            $credit += (float) ($line['credit'] ?? 0);
        }
        self::assertEqualsWithDelta($debit, $credit, 0.001);
        self::assertSame('621', $lines[0]['code']);
        self::assertSame('411', end($lines)['code']);
    }

    public function testAccrualAggregatesEntriesAndBonusAndIncomeTax(): void
    {
        $captured = [];
        // gratificación: gross 3000 + bonus 270 - ONP 390 = 2880; plus quinta 100 on the second entry
        $entries = [
            $this->entry('3000.00', '270.00', '0.00', '390.00', '0.00', '0.00', '2880.00'),
            $this->entry('5000.00', '0.00', '450.00', '0.00', '600.00', '100.00', '4300.00'),
        ];
        $service = $this->service($entries, $captured);

        $service->postAccrual($this->payrollRun());

        $lines = $this->postingService()->linesFor($captured[0]);
        $debit = $credit = 0.0;
        foreach ($lines as $line) {
            $debit += (float) ($line['debit'] ?? 0);
            $credit += (float) ($line['credit'] ?? 0);
        }
        self::assertEqualsWithDelta(8720.0, $debit, 0.001);
        self::assertEqualsWithDelta($debit, $credit, 0.001);
    }

    public function testPaymentUsesCashOrBankAccount(): void
    {
        $captured = [];
        $service = $this->service([$this->entry(
            '1000.00',
            '0.00',
            '90.00',
            '130.00',
            '0.00',
            '0.00',
            '870.00',
        )], $captured);

        $service->postPayment($this->payrollRun(), PaymentMethod::CASH);
        $service->postPayment($this->payrollRun());

        self::assertSame(EconomicEvent::PAYROLL_PAYMENT_CASH, $captured[0]->kind);
        self::assertSame(EconomicEvent::PAYROLL_PAYMENT_BANK, $captured[1]->kind);
        self::assertSame('870.00', $captured[0]->total);
        self::assertSame('101', $this->postingService()->linesFor($captured[0])[1]['code']);
        self::assertSame('1041', $this->postingService()->linesFor($captured[1])[1]['code']);
    }

    private function postingService(): DefaultPostingService
    {
        $poster = new ReflectionClass(\App\Accounting\Service\JournalPoster::class)->newInstanceWithoutConstructor();

        return new DefaultPostingService($poster, new NullLogger());
    }

    private function payrollRun(): PayrollRun
    {
        $run = new PayrollRun();
        new ReflectionClass($run)
            ->getProperty('id')
            ->setValue($run, 'run-1');
        new ReflectionClass($run)
            ->getProperty('period')
            ->setValue($run, new DateTimeImmutable('2026-10-01'));

        return $run->setType(PayrollRun::REGULAR)->setApprovedAt(new DateTimeImmutable('2026-10-31'));
    }

    private function entry(
        string $gross,
        string $bonus,
        string $essalud,
        string $onp,
        string $afp,
        string $tax,
        string $net,
    ): PayslipEntry {
        $entry = new PayslipEntry();
        $entry->setGrossSalary($gross);
        $entry->setBonificacionExtraordinaria($bonus);
        $entry->setEssaludEmployerContribution($essalud);
        $entry->setOnpAmount($onp);
        $entry->setAfpMandatoryContribution($afp);
        $entry->setQuintaCategoriaRetention($tax);
        $entry->setNetPay($net);

        return $entry;
    }

    /**
     * @param list<PayslipEntry> $entries
     * @param list<EconomicEvent> $captured
     */
    private function service(array $entries, array &$captured): PayrollAccountingService
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findBy')->willReturn($entries);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use (&$captured): Envelope {
            $captured[] = $message;

            return new Envelope($message);
        });

        return new PayrollAccountingService($entityManager, $bus);
    }
}
