<?php

declare(strict_types=1);

namespace App\Tests\Sales\Reminder;

use App\Contacts\Entity\Customer;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use App\Organization\Entity\Branch;
use App\Organization\Service\CompanyContext;
use App\Sales\Entity\CollectionReminder;
use App\Sales\Entity\SaleRecord;
use App\Sales\Port\SaleDocumentLinkInterface;
use App\Sales\Reminder\CollectionReminderService;
use App\Sales\Reminder\ReminderChannel;
use App\Sales\Reminder\ReminderContextFactory;
use App\Sales\Reminder\ReminderEmailSender;
use App\Sales\Reminder\ReminderException;
use App\Sales\Reminder\ReminderMessageComposer;
use App\Sales\Reminder\ReminderRecipientResolver;
use App\Sales\Reminder\ReminderSaleGuard;
use App\Sales\Reminder\ReminderStatus;
use App\Sales\Reminder\ReminderTone;
use App\Sales\Repository\CollectionReminderRepository;
use App\Sales\Repository\SaleRecordRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

final class CollectionReminderServiceTest extends TestCase
{
    private const string SALE_ID = '01a18c84-5280-707e-91c2-960f8d00c652';

    private EntityManagerInterface&MockObject $entityManager;
    private SaleRecordRepository&MockObject $sales;
    private CollectionReminderRepository&MockObject $reminders;
    private MailerInterface&MockObject $mailer;
    private MockClock $clock;
    private string $branchId = 'branch-1';
    private ?string $documentUrl = null;

    /** @var list<CollectionReminder> */
    private array $persisted = [];

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function (object $entity): void {
                if ($entity instanceof CollectionReminder) {
                    $this->persisted[] = $entity;
                }
            });
        $this->sales = $this->createMock(SaleRecordRepository::class);
        $this->reminders = $this->createMock(CollectionReminderRepository::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->clock = new MockClock('2026-11-02 10:00:00', 'America/Lima');
    }

    private function customer(
        ?string $phone = '954321678',
        ?string $email = 'rosa@cliente.pe',
        ?string $contact = 'Rosa Quispe',
    ): Customer {
        return new Customer()
            ->setRuc('20100000001')
            ->setBusinessName('Bodega San José')
            ->setPhone($phone)
            ->setEmail($email)
            ->setCollectionContactName($contact);
    }

    private function sale(
        ?Customer $customer = null,
        string $balance = '845.00',
        string $status = 'COMPLETED',
        string $branch = 'branch-1',
    ): SaleRecord&MockObject {
        $branchEntity = $this->createMock(Branch::class);
        $branchEntity->method('getId')->willReturn($branch);

        $sale = $this->createMock(SaleRecord::class);
        $sale->method('getBranch')->willReturn($branchEntity);
        $sale->method('getStatus')->willReturn($status);
        $sale->method('getBalanceDue')->willReturn($balance);
        $sale->method('getCurrency')->willReturn('PEN');
        $sale->method('getCustomer')->willReturn($customer ?? $this->customer());
        $sale->method('getInvoiceFullNumber')->willReturn('F001-00000262');
        $sale->method('getDueDate')->willReturn(new DateTimeImmutable('2026-09-05'));
        $sale->method('getTaxDocumentId')->willReturn(null);
        $sale->method('getDocumentType')->willReturn(null);
        $this->sales->method('find')->with(self::SALE_ID)->willReturn($sale);

        return $sale;
    }

    private function service(): CollectionReminderService
    {
        $branchContext = $this->createMock(BranchContextServiceInterface::class);
        $branchContext->method('getCurrentBranchId')->willReturn($this->branchId);
        $company = $this->createMock(CompanyContext::class);
        $company->method('getName')->willReturn('Compu Express');
        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(7, 'maria'));

        return new CollectionReminderService(
            $this->entityManager,
            $this->reminders,
            new ReminderSaleGuard($this->sales, $branchContext),
            new ReminderRecipientResolver(),
            new ReminderMessageComposer(),
            new ReminderContextFactory($company, $this->documentLink(), $this->clock),
            new ReminderEmailSender($this->mailer, 'noreply@test.pe'),
            $security,
            $this->clock,
        );
    }

    private function documentLink(): SaleDocumentLinkInterface
    {
        $link = $this->createMock(SaleDocumentLinkInterface::class);
        $link->method('publicUrl')->willReturn($this->documentUrl);

        return $link;
    }

    private function previousReminder(
        string $when,
        ReminderChannel $channel = ReminderChannel::Whatsapp,
    ): CollectionReminder {
        return new CollectionReminder(
            self::SALE_ID,
            1,
            $channel,
            ReminderTone::Direct,
            ReminderStatus::Prepared,
            'x',
            null,
            null,
            null,
            7,
            new DateTimeImmutable($when),
        );
    }

    public function testPreviewWhatsappPrepares51PrefixedLinkAndOverdueDays(): void
    {
        $this->sale();
        $this->reminders->method('findLast')->willReturn(null);

        $preview = $this->service()->preview(self::SALE_ID, ReminderChannel::Whatsapp, ReminderTone::Direct);

        self::assertSame('Rosa Quispe', $preview['recipient']['name']);
        self::assertSame('51954321678', $preview['recipient']['phone']);
        self::assertSame(58, $preview['daysOverdue']);
        self::assertStringStartsWith('https://wa.me/51954321678?text=', (string) $preview['whatsappUrl']);
        self::assertStringContainsString('S%2F%20845.00', (string) $preview['whatsappUrl']);
        self::assertNull($preview['subject']);
        self::assertNull($preview['lastReminder']);
    }

    public function testPreviewEmailIncludesSubjectAndNoWhatsappLink(): void
    {
        $this->sale();
        $this->reminders->method('findLast')->willReturn($this->previousReminder('2026-10-20 15:00:00'));

        $preview = $this->service()->preview(self::SALE_ID, ReminderChannel::Email, ReminderTone::Friendly);

        self::assertSame('Recordatorio de pago: F001-00000262', $preview['subject']);
        self::assertNull($preview['whatsappUrl']);
        self::assertSame('whatsapp', $preview['lastReminder']['channel']);
    }

    public function testFallsBackToTheBusinessNameWithoutAContactPerson(): void
    {
        $this->sale($this->customer(contact: null));
        $this->reminders->method('findLast')->willReturn(null);

        $preview = $this->service()->preview(self::SALE_ID, ReminderChannel::Whatsapp, ReminderTone::Direct);

        self::assertSame('Bodega San José', $preview['recipient']['name']);
    }

    public function testMissingChannelDataIsAnUnprocessableRequest(): void
    {
        $this->sale($this->customer(phone: 'sin telefono', email: null));

        foreach ([ReminderChannel::Whatsapp, ReminderChannel::Email] as $channel) {
            try {
                $this->service()->preview(self::SALE_ID, $channel, ReminderTone::Direct);
                self::fail('Expected a missing recipient error for ' . $channel->value);
            } catch (ReminderException $exception) {
                self::assertSame(422, $exception->status);
            }
        }
    }

    public function testSaleFromAnotherBranchIsNotFound(): void
    {
        $this->sale(branch: 'another-branch');

        $this->expectException(ReminderException::class);
        $this->expectExceptionMessage('No se encontró la venta');
        $this->service()->preview(self::SALE_ID, ReminderChannel::Whatsapp, ReminderTone::Direct);
    }

    public function testNothingToCollectWhenVoidedOrPaid(): void
    {
        foreach ([['COMPLETED', '0.00'], ['VOIDED', '100.00']] as [$status, $balance]) {
            $this->sales = $this->createMock(SaleRecordRepository::class);
            $this->sale(status: $status, balance: $balance);
            try {
                $this->service()->preview(self::SALE_ID, ReminderChannel::Whatsapp, ReminderTone::Direct);
                self::fail("Expected a 409 for {$status} / {$balance}");
            } catch (ReminderException $exception) {
                self::assertSame(409, $exception->status);
            }
        }
    }

    public function testRecordingWhatsappStoresItAsPreparedWithAuthorAndNoEmailSent(): void
    {
        $this->sale();
        $this->reminders->method('findLast')->willReturn(null);
        $this->mailer->expects(self::never())->method('send');

        $outcome = $this->service()->record(
            self::SALE_ID,
            ReminderChannel::Whatsapp,
            ReminderTone::Direct,
            null,
            false,
        );

        self::assertSame(ReminderStatus::Prepared, $outcome->reminder->getStatus());
        self::assertSame(7, $outcome->reminder->getCreatedByUserId());
        self::assertSame('51954321678', $outcome->reminder->getRecipientPhone());
        self::assertNotNull($outcome->whatsappUrl);
        self::assertCount(1, $this->persisted);
    }

    public function testRecordingEmailSendsItAndMarksItSent(): void
    {
        $this->sale();
        $this->reminders->method('findLast')->willReturn(null);
        $this->mailer->expects(self::once())->method('send');

        $outcome = $this->service()->record(self::SALE_ID, ReminderChannel::Email, ReminderTone::Friendly, null, false);

        self::assertSame(ReminderStatus::Sent, $outcome->reminder->getStatus());
        self::assertSame('rosa@cliente.pe', $outcome->reminder->getRecipientEmail());
        self::assertNull($outcome->whatsappUrl);
    }

    public function testAFailedEmailIsStillRecordedWithTheReason(): void
    {
        $this->sale();
        $this->reminders->method('findLast')->willReturn(null);
        $this->mailer->method('send')->willThrowException(new TransportException("Connection\nrefused"));

        $outcome = $this->service()->record(self::SALE_ID, ReminderChannel::Email, ReminderTone::Friendly, null, false);

        self::assertSame(ReminderStatus::Failed, $outcome->reminder->getStatus());
        self::assertSame('No se pudo enviar el correo: Connection refused', $outcome->reminder->getFailureReason());
        self::assertCount(1, $this->persisted);
    }

    public function testASecondReminderWithinTwentyFourHoursIsRejectedUnlessForced(): void
    {
        $this->sale();
        $this->reminders->method('findLast')->willReturn($this->previousReminder('2026-11-02 08:00:00'));

        try {
            $this->service()->record(self::SALE_ID, ReminderChannel::Whatsapp, ReminderTone::Direct, null, false);
            self::fail('Expected the frequency rule to reject it');
        } catch (ReminderException $exception) {
            self::assertSame(409, $exception->status);
            self::assertNotNull($exception->lastReminderAt);
            // The reminder was stored at 08:00 UTC; the message must speak in Lima time (UTC-5).
            self::assertStringContainsString('02/11/2026 03:00', $exception->getMessage());
        }
        self::assertCount(0, $this->persisted);

        $forced = $this->service()->record(self::SALE_ID, ReminderChannel::Whatsapp, ReminderTone::Direct, null, true);
        self::assertSame(ReminderStatus::Prepared, $forced->reminder->getStatus());
    }

    public function testAReminderOlderThanTwentyFourHoursIsAllowed(): void
    {
        $this->sale();
        $this->reminders->method('findLast')->willReturn($this->previousReminder('2026-11-01 09:00:00'));

        $outcome = $this->service()->record(
            self::SALE_ID,
            ReminderChannel::Whatsapp,
            ReminderTone::Direct,
            null,
            false,
        );

        self::assertCount(1, $this->persisted);
        self::assertNotNull($outcome->whatsappUrl);
    }

    public function testCustomBodyIsUsedAndValidated(): void
    {
        $this->sale();
        $this->reminders->method('findLast')->willReturn(null);

        $outcome = $this->service()->record(
            self::SALE_ID,
            ReminderChannel::Whatsapp,
            ReminderTone::Direct,
            '  Hola, ¿novedades del pago?  ',
            false,
        );
        self::assertSame('Hola, ¿novedades del pago?', $outcome->reminder->getBody());

        foreach (['   ', str_repeat('a', 2001)] as $invalid) {
            try {
                $this->service()->record(
                    self::SALE_ID,
                    ReminderChannel::Whatsapp,
                    ReminderTone::Direct,
                    $invalid,
                    true,
                );
                self::fail('Expected an invalid body error');
            } catch (ReminderException $exception) {
                self::assertSame(422, $exception->status);
            }
        }
    }

    public function testHistoryNeedsASaleOrACustomer(): void
    {
        $this->expectException(ReminderException::class);
        $this->service()->history(null, null);
    }

    public function testPreviewQuotesThePublicDocumentLinkWhenTheSaleHasOne(): void
    {
        $this->documentUrl = 'https://erp.test/receipt/abc';
        $this->sale();
        $this->reminders->method('findLast')->willReturn(null);

        $preview = $this->service()->preview(self::SALE_ID, ReminderChannel::Whatsapp, ReminderTone::Direct);

        self::assertSame('https://erp.test/receipt/abc', $preview['documentUrl']);
        self::assertStringContainsString('https://erp.test/receipt/abc', $preview['body']);
    }
}
