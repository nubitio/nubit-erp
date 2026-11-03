<?php

declare(strict_types=1);

namespace App\Tests\Sales\Reminder;

use App\Sales\Reminder\ReminderContext;
use App\Sales\Reminder\ReminderMessageComposer;
use App\Sales\Reminder\ReminderTone;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReminderMessageComposerTest extends TestCase
{
    private ReminderMessageComposer $composer;

    protected function setUp(): void
    {
        $this->composer = new ReminderMessageComposer();
    }

    private function context(
        int $daysOverdue = 43,
        ?string $url = 'https://erp.test/receipt/abc',
        ?string $number = 'F001-00000262',
        ?DateTimeImmutable $due = new DateTimeImmutable('2026-09-05'),
    ): ReminderContext {
        return new ReminderContext('Compu Express', 'Rosa Quispe', $number, 'S/ 845.00', $due, $daysOverdue, $url);
    }

    public function testDirectToneMentionsDocumentAmountDueDateAndLink(): void
    {
        $body = $this->composer->body($this->context(), ReminderTone::Direct);

        self::assertStringContainsString('Hola Rosa Quispe, le escribimos de Compu Express.', $body);
        self::assertStringContainsString('El comprobante F001-00000262 por S/ 845.00 venció el 05/09/2026', $body);
        self::assertStringContainsString('https://erp.test/receipt/abc', $body);
        self::assertStringContainsString('Si ya pagó', $body);
    }

    public function testFriendlyToneIsSofterAndAsksToIgnoreIfAlreadyPaid(): void
    {
        $body = $this->composer->body($this->context(), ReminderTone::Friendly);

        self::assertStringContainsString('te escribimos de Compu Express', $body);
        self::assertStringContainsString('ignora este mensaje', $body);
        self::assertStringNotContainsString('último aviso', $body);
    }

    public function testFinalToneStatesDaysOverdue(): void
    {
        $body = $this->composer->body($this->context(), ReminderTone::Final);

        self::assertStringContainsString('último aviso', $body);
        self::assertStringContainsString('(43 días de atraso)', $body);
    }

    public function testSingularDayOverdue(): void
    {
        $body = $this->composer->body($this->context(daysOverdue: 1), ReminderTone::Final);

        self::assertStringContainsString('(1 día de atraso)', $body);
    }

    public function testNotYetDueSaysItWillBecomeDue(): void
    {
        $body = $this->composer->body(
            $this->context(daysOverdue: 0, due: new DateTimeImmutable('2026-12-01')),
            ReminderTone::Friendly,
        );

        self::assertStringContainsString('vence el 01/12/2026', $body);
        self::assertStringNotContainsString('venció', $body);
    }

    public function testOmitsTheLinkWhenThereIsNoPublicDocument(): void
    {
        $body = $this->composer->body($this->context(url: null), ReminderTone::Direct);

        self::assertStringNotContainsString('http', $body);
        self::assertStringNotContainsString('Puede ver el comprobante', $body);
    }

    public function testSaleWithoutVoucherNumberStillReadsNaturally(): void
    {
        $body = $this->composer->body($this->context(number: null, due: null, daysOverdue: 0), ReminderTone::Friendly);

        self::assertStringContainsString('tu compra por S/ 845.00 está pendiente de pago', $body);
    }

    public function testSubjectUsesTheVoucherNumberOrAGenericPhrase(): void
    {
        self::assertSame('Recordatorio de pago: F001-00000262', $this->composer->subject($this->context()));
        self::assertSame(
            'Recordatorio de pago: su saldo pendiente',
            $this->composer->subject($this->context(number: null)),
        );
    }

    public function testFormalTonesNeverUseTheInformalTu(): void
    {
        $context = $this->context(number: null, due: null, daysOverdue: 0);

        self::assertStringContainsString('Su compra', $this->composer->body($context, ReminderTone::Direct));
        self::assertStringContainsString('Su compra', $this->composer->body($context, ReminderTone::Final));
        self::assertStringNotContainsString(
            'tu compra',
            strtolower($this->composer->body($context, ReminderTone::Direct)),
        );
    }

    public function testDirectToneOnlySaysItIsStillPendingWhenItIsOverdue(): void
    {
        $notYetDue = $this->composer->body(
            $this->context(daysOverdue: 0, due: new DateTimeImmutable('2026-12-01')),
            ReminderTone::Direct,
        );
        $overdue = $this->composer->body($this->context(), ReminderTone::Direct);

        self::assertStringNotContainsString('sigue pendiente', $notYetDue);
        self::assertStringContainsString('vence el 01/12/2026. ¿Nos confirma', $notYetDue);
        self::assertStringContainsString('venció el 05/09/2026 y sigue pendiente', $overdue);
    }
}
