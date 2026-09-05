<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Catalog\Repository\PaymentMethodRepository;
use Nubit\Platform\Exception\ServiceException;

final readonly class SettlementPaymentMethodResolver
{
    public function __construct(
        private ?PaymentMethodRepository $paymentMethodRepository = null,
    ) {}

    /**
     * @param array<string, mixed> $settlement
     */
    public function id(array $settlement): ?int
    {
        return $this->normalizeId(
            $settlement['payment_method_id']
                ?? $settlement['paymentMethodId']
                ?? $settlement['methodId']
                ?? null,
        );
    }

    private function normalizeId(mixed $raw): ?int
    {
        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $trimmed = trim($raw);
        if (ctype_digit($trimmed)) {
            return (int) $trimmed;
        }

        $matches = [];

        return preg_match('/(\\d+)$/', $trimmed, $matches) === 1 ? (int) $matches[1] : null;
    }

    public function requireCollected(?int $methodId, int $index): int
    {
        if ($methodId === null) {
            throw new ServiceException(sprintf('Debe indicar el método del pago en posición %d.', $index));
        }
        if ($this->paymentMethodRepository === null) {
            return $methodId;
        }

        $method = $this->paymentMethodRepository->find($methodId);
        if (!$method instanceof PaymentMethod) {
            throw new ServiceException('Método de pago no encontrado.');
        }
        if ($method->getSettlementBehavior() !== SettlementBehavior::COLLECTED) {
            throw new ServiceException('Una asignación por cobrar no puede registrarse como pago recibido.');
        }

        return $methodId;
    }
}
