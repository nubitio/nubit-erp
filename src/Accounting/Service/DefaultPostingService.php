<?php

declare(strict_types=1);

namespace App\Accounting\Service;

use App\Accounting\Entity\JournalEntry;
use App\Accounting\Message\EconomicEvent;
use App\Efact\Domain\Money;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class DefaultPostingService
{
    public const string CASH = '101';

    public const string RECEIVABLE = '121';

    public const string BANK = '1041';

    public const string INVENTORY = '201';

    public const string IGV = '40111';

    public const string PAYABLE = '421';

    public const string COGS = '691';

    public const string SALES = '70111';

    public const string OTHER_INCOME = '759';

    public const string OTHER_EXPENSE = '659';

    public const string CUSTOMER_ADVANCE = '122';

    public const string OPENING_EQUITY = '591';

    public const string PAYROLL_SALARY_EXPENSE = '621';

    public const string PAYROLL_OTHER_PAY_EXPENSE = '622';

    public const string PAYROLL_SOCIAL_SECURITY_EXPENSE = '627';

    public const string INCOME_TAX_5TH_CATEGORY = '4017';

    public const string ESSALUD_PAYABLE = '4031';

    public const string ONP_PAYABLE = '4032';

    public const string AFP_PAYABLE = '407';

    public const string SALARIES_PAYABLE = '411';

    public const string PAYROLL_GROSS = 'gross';

    public const string PAYROLL_BONUS = 'bonus';

    public const string PAYROLL_ESSALUD_EMPLOYER = 'essalud_employer';

    public const string PAYROLL_AFP = 'afp';

    public const string PAYROLL_ONP = 'onp';

    public const string PAYROLL_INCOME_TAX = 'income_tax_5th';

    public const string PAYROLL_NET = 'net';

    public function __construct(
        private JournalPoster $journalPoster,
        private LoggerInterface $logger,
    ) {}

    public function post(EconomicEvent $event): ?JournalEntry
    {
        try {
            $date = new DateTimeImmutable($event->occurredOn);
            $lines = $this->linesFor($event);

            return $this->journalPoster->post(
                $event->source,
                $event->sourceId,
                $date,
                $event->description,
                $lines,
                $event->currency,
            );
        } catch (Throwable $exception) {
            $this->logger->error('Accounting could not post an economic event', [
                'source' => $event->source,
                'source_id' => $event->sourceId,
                'kind' => $event->kind,
                'error' => $exception->getMessage(),
            ]);

            // EconomicEvent is routed synchronously. Swallowing an unexpected
            // persistence/connection failure would commit the source business
            // fact without its journal entry. Propagate it so the caller's
            // transaction can fail closed; the backfill command deliberately
            // catches and reports per-event failures at its boundary.
            throw $exception;
        }
    }

    /**
     * @return list<array{code: string, debit?: string, credit?: string, memo?: string}>
     */
    public function linesFor(EconomicEvent $event): array
    {
        $total = Money::money($event->total);
        $tax = Money::money($event->tax);
        if ($tax->isGreaterThan($total)) {
            $tax = $total;
        }
        $net = Money::sub($total, $tax);
        $cogs = Money::money($event->cogs);

        return match ($event->kind) {
            EconomicEvent::SALE_CASH => $this->saleLines(self::CASH, $total, $net, $tax, $cogs),
            EconomicEvent::SALE_CREDIT => $this->saleLines(self::RECEIVABLE, $total, $net, $tax, $cogs),
            EconomicEvent::PURCHASE => [
                ['code' => self::INVENTORY, 'debit' => Money::toString($net), 'memo' => 'Ingreso de mercadería'],
                ['code' => self::IGV, 'debit' => Money::toString($tax), 'memo' => 'IGV crédito fiscal'],
                ['code' => self::PAYABLE, 'credit' => Money::toString($total), 'memo' => 'Factura por pagar'],
            ],
            EconomicEvent::CASH_IN => [
                ['code' => self::CASH, 'debit' => Money::toString($total)],
                ['code' => self::OTHER_INCOME, 'credit' => Money::toString($total)],
            ],
            EconomicEvent::CASH_OUT => [
                ['code' => self::OTHER_EXPENSE, 'debit' => Money::toString($total)],
                ['code' => self::CASH, 'credit' => Money::toString($total)],
            ],
            EconomicEvent::RECEIVABLE_COLLECT_CASH => [
                [
                    'code' => self::CASH,
                    'debit' => Money::toString($total),
                    'memo' => 'Cobranza de cuenta por cobrar (efectivo)',
                ],
                [
                    'code' => self::RECEIVABLE,
                    'credit' => Money::toString($total),
                    'memo' => 'Aplicación a cuenta por cobrar',
                ],
            ],
            EconomicEvent::RECEIVABLE_COLLECT_BANK => [
                [
                    'code' => self::BANK,
                    'debit' => Money::toString($total),
                    'memo' => 'Cobranza de cuenta por cobrar (banco)',
                ],
                [
                    'code' => self::RECEIVABLE,
                    'credit' => Money::toString($total),
                    'memo' => 'Aplicación a cuenta por cobrar',
                ],
            ],
            EconomicEvent::CUSTOMER_ADVANCE_RECEIVED_CASH => [
                ['code' => self::CASH, 'debit' => Money::toString($total), 'memo' => 'Anticipo de cliente (efectivo)'],
                [
                    'code' => self::CUSTOMER_ADVANCE,
                    'credit' => Money::toString($total),
                    'memo' => 'Anticipos de clientes',
                ],
            ],
            EconomicEvent::CUSTOMER_ADVANCE_RECEIVED_BANK => [
                ['code' => self::BANK, 'debit' => Money::toString($total), 'memo' => 'Anticipo de cliente (banco)'],
                [
                    'code' => self::CUSTOMER_ADVANCE,
                    'credit' => Money::toString($total),
                    'memo' => 'Anticipos de clientes',
                ],
            ],
            EconomicEvent::CUSTOMER_ADVANCE_APPLIED => [
                [
                    'code' => self::CUSTOMER_ADVANCE,
                    'debit' => Money::toString($total),
                    'memo' => 'Aplicación de anticipo',
                ],
                [
                    'code' => self::RECEIVABLE,
                    'credit' => Money::toString($total),
                    'memo' => 'Aplicación a cuenta por cobrar',
                ],
            ],
            EconomicEvent::OPENING_RECEIVABLE => [
                ['code' => self::RECEIVABLE, 'debit' => Money::toString($total), 'memo' => 'Saldo inicial por cobrar'],
                [
                    'code' => self::OPENING_EQUITY,
                    'credit' => Money::toString($total),
                    'memo' => 'Contrapartida de saldo inicial',
                ],
            ],
            EconomicEvent::PAYABLE_PAYMENT_CASH => $this->payablePaymentLines(self::CASH, $total, false),
            EconomicEvent::PAYABLE_PAYMENT_BANK => $this->payablePaymentLines(self::BANK, $total, false),
            EconomicEvent::PAYABLE_PAYMENT_REVERSE_CASH => $this->payablePaymentLines(self::CASH, $total, true),
            EconomicEvent::PAYABLE_PAYMENT_REVERSE_BANK => $this->payablePaymentLines(self::BANK, $total, true),
            EconomicEvent::SUPPLIER_BILL => [
                [
                    'code' => self::OTHER_EXPENSE,
                    'debit' => Money::toString($total),
                    'memo' => 'Gasto de factura de proveedor',
                ],
                ['code' => self::PAYABLE, 'credit' => Money::toString($total), 'memo' => 'Factura por pagar'],
            ],
            EconomicEvent::SUPPLIER_BILL_REVERSE => [
                [
                    'code' => self::PAYABLE,
                    'debit' => Money::toString($total),
                    'memo' => 'Reversión de factura de proveedor',
                ],
                [
                    'code' => self::OTHER_EXPENSE,
                    'credit' => Money::toString($total),
                    'memo' => 'Reversión del gasto de proveedor',
                ],
            ],
            EconomicEvent::PAYROLL_ACCRUAL => $this->payrollAccrualLines($event->components),
            EconomicEvent::PAYROLL_PAYMENT_CASH => $this->payrollPaymentLines(self::CASH, $total),
            EconomicEvent::PAYROLL_PAYMENT_BANK => $this->payrollPaymentLines(self::BANK, $total),
            default => throw new InvalidArgumentException(sprintf(
                'Tipo de hecho económico no soportado: %s',
                $event->kind,
            )),
        };
    }

    /**
     * Devengo de planilla: gasto de personal contra obligaciones con el
     * trabajador (411), AFP (407), ONP (4032), quinta categoría (4017) y
     * EsSalud del empleador (4031).
     *
     * @param array<string, string> $components
     *
     * @return list<array{code: string, debit?: string, credit?: string, memo?: string}>
     */
    private function payrollAccrualLines(array $components): array
    {
        $amount = static fn(string $key): BigDecimal => Money::money($components[$key] ?? '0');

        return [
            [
                'code' => self::PAYROLL_SALARY_EXPENSE,
                'debit' => Money::toString($amount(self::PAYROLL_GROSS)),
                'memo' => 'Remuneraciones del periodo',
            ],
            [
                'code' => self::PAYROLL_OTHER_PAY_EXPENSE,
                'debit' => Money::toString($amount(self::PAYROLL_BONUS)),
                'memo' => 'Bonificación extraordinaria',
            ],
            [
                'code' => self::PAYROLL_SOCIAL_SECURITY_EXPENSE,
                'debit' => Money::toString($amount(self::PAYROLL_ESSALUD_EMPLOYER)),
                'memo' => 'EsSalud a cargo del empleador',
            ],
            [
                'code' => self::AFP_PAYABLE,
                'credit' => Money::toString($amount(self::PAYROLL_AFP)),
                'memo' => 'Aportes AFP retenidos',
            ],
            [
                'code' => self::ONP_PAYABLE,
                'credit' => Money::toString($amount(self::PAYROLL_ONP)),
                'memo' => 'Aportes ONP retenidos',
            ],
            [
                'code' => self::INCOME_TAX_5TH_CATEGORY,
                'credit' => Money::toString($amount(self::PAYROLL_INCOME_TAX)),
                'memo' => 'Retención de renta de quinta categoría',
            ],
            [
                'code' => self::ESSALUD_PAYABLE,
                'credit' => Money::toString($amount(self::PAYROLL_ESSALUD_EMPLOYER)),
                'memo' => 'EsSalud por pagar',
            ],
            [
                'code' => self::SALARIES_PAYABLE,
                'credit' => Money::toString($amount(self::PAYROLL_NET)),
                'memo' => 'Remuneraciones por pagar',
            ],
        ];
    }

    /** @return list<array{code: string, debit?: string, credit?: string, memo?: string}> */
    private function payrollPaymentLines(string $cashAccount, BigDecimal $total): array
    {
        return [
            ['code' => self::SALARIES_PAYABLE, 'debit' => Money::toString($total), 'memo' => 'Pago de planilla'],
            ['code' => $cashAccount, 'credit' => Money::toString($total), 'memo' => 'Salida por pago de planilla'],
        ];
    }

    /** @return list<array{code: string, debit?: string, credit?: string, memo?: string}> */
    private function payablePaymentLines(string $cashAccount, BigDecimal $total, bool $reverse): array
    {
        if ($reverse) {
            return [
                ['code' => $cashAccount, 'debit' => Money::toString($total), 'memo' => 'Reversión de pago a proveedor'],
                [
                    'code' => self::PAYABLE,
                    'credit' => Money::toString($total),
                    'memo' => 'Reapertura de cuenta por pagar',
                ],
            ];
        }

        return [
            ['code' => self::PAYABLE, 'debit' => Money::toString($total), 'memo' => 'Aplicación de pago a proveedor'],
            ['code' => $cashAccount, 'credit' => Money::toString($total), 'memo' => 'Salida por pago a proveedor'],
        ];
    }

    /**
     * @return list<array{code: string, debit?: string, credit?: string, memo?: string}>
     */
    private function saleLines(
        string $debitAccount,
        BigDecimal $total,
        BigDecimal $net,
        BigDecimal $tax,
        BigDecimal $cogs,
    ): array {
        $lines = [
            ['code' => $debitAccount, 'debit' => Money::toString($total), 'memo' => 'Cobro / cuenta por cobrar'],
            ['code' => self::SALES, 'credit' => Money::toString($net), 'memo' => 'Venta neta'],
            ['code' => self::IGV, 'credit' => Money::toString($tax), 'memo' => 'IGV débito fiscal'],
        ];

        if (!$cogs->isZero()) {
            $lines[] = ['code' => self::COGS, 'debit' => Money::toString($cogs), 'memo' => 'Costo de lo vendido'];
            $lines[] = [
                'code' => self::INVENTORY,
                'credit' => Money::toString($cogs),
                'memo' => 'Salida de mercadería',
            ];
        }

        return $lines;
    }
}
