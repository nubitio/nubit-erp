<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Identity\Entity\User;
use DateTimeImmutable;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

/** Turns the JSON of POST /api/sales/collections into a {@see CollectionRequest}, or a 400/404 that says what is wrong. */
readonly class CollectionRequestParser
{
    public function __construct(
        private TenderParser $tenderParser,
    ) {}

    public function parse(Request $request, User $user, int $userId): CollectionRequest
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            throw new BadRequestHttpException('El cuerpo de la solicitud debe ser JSON.');
        }

        $tenders = array_map($this->tenderParser->parse(...), array_values((array) ($body['tenders'] ?? [])));
        if ($tenders === []) {
            throw new BadRequestHttpException('Indica al menos un medio de pago.');
        }

        return new CollectionRequest(
            customerId: (int) ($body['customerId'] ?? 0),
            currency: strtoupper((string) ($body['currency'] ?? 'PEN')),
            receivedOn: $this->date((string) ($body['receivedOn'] ?? '')),
            tenders: $tenders,
            allocations: array_map(static fn(mixed $line): array => [
                'installmentId' => (string) (((array) $line)['installmentId'] ?? ''),
                'amount' => (string) (((array) $line)['amount'] ?? ''),
            ], array_values((array) ($body['allocations'] ?? []))),
            idempotencyKey: $this->idempotencyKey($request, $userId),
            createdBy: $user,
        );
    }

    private function date(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value === '' ? 'today' : $value);
        } catch (Exception) {
            throw new BadRequestHttpException('La fecha de la cobranza no es válida.');
        }
    }

    private function idempotencyKey(Request $request, int $userId): string
    {
        $key = (string) $request->headers->get('Idempotency-Key', '');
        if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key) !== 1) {
            throw new BadRequestHttpException(
                'Idempotency-Key obligatoria: use 8 a 128 caracteres alfanuméricos, punto, guion o dos puntos.',
            );
        }

        return Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_OID), sprintf('collection:%d:%s', $userId, $key))->toRfc4122();
    }
}
