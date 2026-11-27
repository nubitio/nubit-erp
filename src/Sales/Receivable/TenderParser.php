<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Catalog\Entity\PaymentMethod;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** One entry of `tenders` in the request body, with its payment method resolved. */
readonly class TenderParser
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function parse(mixed $raw): TenderInput
    {
        $fields = (array) $raw;
        $method = $this->entityManager->find(PaymentMethod::class, (int) ($fields['paymentMethodId'] ?? 0));
        if (!$method instanceof PaymentMethod) {
            throw new NotFoundHttpException('Método de pago no encontrado.');
        }

        return new TenderInput(
            method: $method,
            amount: (string) ($fields['amount'] ?? ''),
            moneyAccountId: self::text($fields, 'moneyAccountId'),
            reference: self::text($fields, 'reference'),
        );
    }

    /** @param array<mixed> $fields */
    private static function text(array $fields, string $key): ?string
    {
        $value = (string) ($fields[$key] ?? '');

        return $value === '' ? null : $value;
    }
}
