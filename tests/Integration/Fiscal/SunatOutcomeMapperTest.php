<?php

declare(strict_types=1);

namespace App\Tests\Integration\Fiscal;

use App\Billing\Sunat\Model\Response\DocumentResponse;
use App\Integration\Fiscal\SunatOutcomeMapper;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\CdrResponse;
use Greenter\Model\Response\Error;
use PHPUnit\Framework\TestCase;

final class SunatOutcomeMapperTest extends TestCase
{
    private function response(BillResult $result): DocumentResponse
    {
        $response = new DocumentResponse();
        $response->name = 'F001-1';
        $response->xml = '<x/>';
        $response->hash = 'h';
        $response->sunatResponse = $result;

        return $response;
    }

    public function testAcceptedWhenCdrCodeIsZero(): void
    {
        $cdr = new CdrResponse()
            ->setCode('0')
            ->setDescription('La Factura numero F001-1, ha sido aceptada')
            ->setId('R-1');
        $result = new BillResult()
            ->setSuccess(true)
            ->setCdrResponse($cdr);

        $outcome = new SunatOutcomeMapper()->from($this->response($result));

        self::assertTrue($outcome->accepted);
        self::assertSame('0', $outcome->sunatStatus);
        self::assertSame('R-1', $outcome->cdrReference);
    }

    public function testAcceptedWithObservationsWhenCode4000Plus(): void
    {
        $cdr = new CdrResponse()
            ->setCode('4000')
            ->setDescription('aceptada con observaciones')
            ->setId('R-2');
        $outcome = new SunatOutcomeMapper()->from($this->response(
            new BillResult()
                ->setSuccess(true)
                ->setCdrResponse($cdr),
        ));

        self::assertTrue($outcome->accepted);
        self::assertSame('4000', $outcome->sunatStatus);
    }

    public function testRejectedWhenCdrCodeIsInThe2000Range(): void
    {
        $cdr = new CdrResponse()
            ->setCode('2335')
            ->setDescription('El documento electrónico ingresado ha sido rechazado');
        $outcome = new SunatOutcomeMapper()->from($this->response(
            new BillResult()
                ->setSuccess(false)
                ->setCdrResponse($cdr),
        ));

        self::assertFalse($outcome->accepted);
        self::assertSame('2335', $outcome->sunatStatus);
        self::assertStringContainsString('rechazado', (string) $outcome->message);
    }

    public function testRejectedFromTransportErrorWhenThereIsNoCdr(): void
    {
        $error = new Error()
            ->setCode('1033')
            ->setMessage('El comprobante fue registrado previamente con otros datos');
        $outcome = new SunatOutcomeMapper()->from($this->response(
            new BillResult()
                ->setSuccess(false)
                ->setError($error),
        ));

        self::assertFalse($outcome->accepted);
        self::assertSame('1033', $outcome->sunatStatus);
        self::assertSame('El comprobante fue registrado previamente con otros datos', $outcome->message);
    }
}
