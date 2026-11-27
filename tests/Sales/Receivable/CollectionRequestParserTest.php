<?php

declare(strict_types=1);

namespace App\Tests\Sales\Receivable;

use App\Catalog\Entity\PaymentMethod;
use App\Identity\Entity\User;
use App\Sales\Receivable\CollectionRequestParser;
use App\Sales\Receivable\TenderParser;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CollectionRequestParserTest extends TestCase
{
    public function testAValidBodyBecomesARequestWithAKeyScopedToTheUser(): void
    {
        $request = $this->request([
            'customerId' => 9,
            'tenders' => [['paymentMethodId' => 2, 'amount' => '100', 'reference' => 'OP-1']],
        ], 'abc-12345');

        $parsed = $this->parser()->parse($request, new User(), 7);
        $other = $this->parser()->parse($request, new User(), 8);

        self::assertSame(9, $parsed->customerId);
        self::assertSame('PEN', $parsed->currency);
        self::assertSame('OP-1', $parsed->tenders[0]->reference);
        self::assertNotSame($parsed->idempotencyKey, $other->idempotencyKey);
        self::assertSame($parsed->idempotencyKey, $this->parser()->parse($request, new User(), 7)->idempotencyKey);
    }

    public function testTheKeyIsMandatory(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Idempotency-Key');

        $this->parser()->parse(
            $this->request(['tenders' => [['paymentMethodId' => 2, 'amount' => '1']]], null),
            new User(),
            7,
        );
    }

    public function testAtLeastOneTenderIsRequired(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->parser()->parse($this->request(['customerId' => 9, 'tenders' => []], 'abc-12345'), new User(), 7);
    }

    /** @param array<string, mixed> $body */
    private function request(array $body, ?string $key): Request
    {
        $request = new Request(content: json_encode($body, JSON_THROW_ON_ERROR));
        if ($key !== null) {
            $request->headers->set('Idempotency-Key', $key);
        }

        return $request;
    }

    private function parser(): CollectionRequestParser
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn(new PaymentMethod());

        return new CollectionRequestParser(new TenderParser($em));
    }
}
